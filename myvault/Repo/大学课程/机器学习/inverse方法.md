# 1. 初始化
## 准备容器`data`
容器`data`作用：存储邻域数据
```Python
is_sparse = sp.sparse.issparse(data_row)  
if is_sparse:  
num_cols = data_row.shape[1]  
data = sp.sparse.csr_matrix((num_samples, num_cols), dtype=data_row.dtype)  
else:  
num_cols = data_row.shape[0]  
data = np.zeros((num_samples, num_cols))
```
- **检查数据稀疏性**：首先检查 `data_row` 是否为稀疏格式。
- **创建数据容器**：
    - 如果数据是稀疏的，创建一个与 `data_row` 形状相同的稀疏矩阵来存储扰动数据。
    - 如果数据是密集的，创建一个零矩阵来存储扰动数据。
## 准备索引`categorical_features`
列表`categorical_features`作用：
- 分类特征索引
- 区分哪些特征是分类的，哪些是数值的
```Python
categorical_features = range(num_cols)
```
# 2. 生成邻域数据
## 对于连续型特征：
对每个连续特征：生成指定个数（`num_samples`）个***正态分布***的邻域数据
核心公式：<u>邻域数据=标准正态分布随机数×标准差+样本中心</u>

**（1）若未进行离散化处理**，即discretizer =None：
>即存在连续特征，需要进行连续型特征的处理

```Python
        if self.discretizer is None:
            instance_sample = data_row # instance_sample:待扰动样本
            scale = self.scaler.scale_ # 标准差
```
>`scaler.scale_`：[[sklearn中的标准差scale]]
```Python
# 继续上述代码
            mean = self.scaler.mean_ # 均值
            
            if is_sparse: # 若数据稀疏，则只筛选出非零数据用于后续操作
                # Perturb only the non-zero values
                non_zero_indexes = data_row.nonzero()[1]
                num_cols = len(non_zero_indexes)
                # 筛选去除所有0后，更新待扰动样本、scale和mean
                instance_sample = data_row[:, non_zero_indexes]
                scale = scale[non_zero_indexes] 
                mean = mean[non_zero_indexes]
                
            data = self.random_state.normal( 
                0, 1, num_samples * num_cols).reshape(
                num_samples, num_cols) # 生成正态分布的随机样本

			# 选择扰动方式
            if self.sample_around_instance: # 该参数是init整个解释器时人工选择的
                data = data * scale + instance_sample # 方式1：围绕样本本身
            else:
                data = data * scale + mean # 方式2：围绕均值

			# 问题1
            if is_sparse: # 若数据稀疏，需要将data转换为CSR格式，便于后续输入预测模型
                if num_cols == 0:
                    data = sp.sparse.csr_matrix((num_samples,
                                                 data_row.shape[1]),
                                                dtype=data_row.dtype)
                else:
                    indexes = np.tile(non_zero_indexes, num_samples)
                    indptr = np.array(
                        range(0, len(non_zero_indexes) * (num_samples + 1),
                              len(non_zero_indexes)))
                    data_1d_shape = data.shape[0] * data.shape[1]
                    data_1d = data.reshape(data_1d_shape)
                    data = sp.sparse.csr_matrix(
                        (data_1d, indexes, indptr),
                        shape=(num_samples, data_row.shape[1]))
            categorical_features = self.categorical_features
            first_row = data_row
```
问题1：[[问题1的GPT解释]]

**（1）若已完成离散化处理**，即discretizer != None：
>即不存在连续型特征，直接跳过连续型特征的处理步骤，进入分类型特征的处理步骤

```Python
        else:
            first_row = self.discretizer.discretize(data_row)
        data[0] = data_row.copy()
        inverse = data.copy()
```

## 对于分类型特征：
根据训练集的分布进行采样：
- 当新生成样本的类别型特征与预测样本相同时，该分类型特征取值为 1
- 否则取值为 0
```Python
        for column in categorical_features:
            values = self.feature_values[column] # 特征的值
            freqs = self.feature_frequencies[column] # 特征的值对应的频率
            # 根据频率随机生成一个特征值
            inverse_column = self.random_state.choice(values, size=num_samples,
                                                      replace=True, p=freqs) 
            # 按二分赋值（规则见上）
            binary_column = (inverse_column == first_row[column]).astype(int) 
            binary_column[0] = 1
            inverse_column[0] = data[0, column]
            data[:, column] = binary_column
            inverse[:, column] = inverse_column
        if self.discretizer is not None:
            inverse[1:] = self.discretizer.undiscretize(inverse[1:])
        inverse[0] = data_row
```

# 3. 返回结果
- 邻域数据`data` ：用于训练局部线性模型
- 邻域数据原始形式 `inverse` ：
	- 对于分类特征：等于原始的分类值
	- 对于连续特征：等于缩放前的值