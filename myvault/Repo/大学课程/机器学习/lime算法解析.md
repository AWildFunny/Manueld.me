#机器学习/lime算法 

References: [黑盒模型事后归因解析：LIME方法_AI_索信达控股_InfoQ精选文章](https://www.infoq.cn/article/tuqsj0wmgakoaupwsxvy)
Cooperations: GPT-4 Turbo
# 什么是lime？
## 概念解释
LIME：Local Interpretable Model-agnostic Explanations "局部可解释模型-不透明的解释器"

>**L**ocal：**只能预测样本的局部**  在想要解释的预测值附近构建可解释的模型，并且该模型在这个点周围的局部效果与复杂模型的效果很接近
>**I**nterpretable：**可被人理解的** 解释器的模型与特征都必须是可解释的，可用局部样本特征解释复杂模型预测结果
>**M**odel-agnostic：**通用的，与模型无关的** 与复杂模型无关，任何模型都可以用LIME进行解释
>**E**xplainations：**解释** 是一种事后解释方法

主要用于解释机器学习模型的预测结果，帮助理解模型是如何做出特定预测的

![[240116-lime算法解析-1.png]]
## 核心原理

>对于局部范围内的相同输入，我们希望两个模型的输出尽可能接近。这里要重点强调**局部范围**的概念，因为实际上Linear Model并不能模拟整个模型，但却可以模拟其中的一个Local Region，这也是LIME可行的原因。

# 算法步骤拆解
演示图见[[lime算法示意图.canvas|lime算法示意图]]
![[240120-lime算法解析-1.png]]
以下为***基于表格数据(Tabular Data)预测结果***的演示
## 数据准备
`explain_instance`函数的参数：
```Python
data_row, # 表格的一行（待解释样本）   
predict_fn, # 预测函数
labels=(1,), # 待解释目标的标签
top_labels=None, # 若为K，则忽略labels参数，为预测概率最高的K个标签生成解释
num_features=10, # 解释中：考虑的最大特征（字段）数目
num_samples=5000, # 领域数据大小（扰动样本数量）
distance_metric='euclidean',  # 用于权重计算的距离度量
model_regressor=None # 用于解释的模型（默认为Ridge回归）
```
具体到例子中：
```Python
exp=explainer.explain_instance(X_test[0],predict_fn_xgb,num_features=13) #使用解释器预测了第一个样本
```
- 待解释样本：测试集中的一行`X_test[0]`
- 预测函数：XGB分类器`predict_fn_xgb`
- 考虑的最大特征数目：13
### 处理待解释样本data_row
若是稀疏格式但不是 [[CSR存储格式]]（Compressed Sparse Row），则将其转换为 CSR 格式。
```Python
if sp.sparse.issparse(data_row) and not sp.sparse.isspmatrix_csr(data_row):  
data_row = data_row.tocsr()
```
这一步的作用：
- 统一数据格式，便于输入预测模型
## 生成邻域数据
调用inverse方法生成邻域数据
```Python
data, inverse = self.__data_inverse(data_row, num_samples)
```
inverse方法内部流程：详见[[inverse方法]]

生成结果：
- 邻域数据`data` ：用于训练局部线性模型
- 反标准化处理后的邻域数据 `inverse` ：
	- 对于分类特征：等于原始的分类值
	- 对于连续特征：等于缩放前的值

>为什么生成邻域数据`data`的同时，要生成`inverse`?
>回答：[[inverse与data的区别]] （联系[[lime算法解析#获取预测]]）
## 标准化处理
处理流程：
- 稀疏数据：乘以标准差
- 密集数据：减去均值后除以标准差
```Python
if sp.sparse.issparse(data): # 对于稀疏数据
    # Note in sparse case we don't subtract mean since data would become dense
    scaled_data = data.multiply(self.scaler.scale_) # 乘以对应特征的标准差
    # Multiplying with csr matrix can return a coo sparse matrix
    if not sp.sparse.isspmatrix_csr(scaled_data): # 转换为CSR格式
        scaled_data = scaled_data.tocsr()
else: # 对于密集数据
    scaled_data = (data - self.scaler.mean_) / self.scaler.scale_ # 减去均值，再除以标准差
```
作用：
- 将data标准化，确保其分布与原始训练数据类似
- 便于计算距离

## 计算距离
求 **标准化后的邻域数据`scaled_data`** 
与 **原始数据点（即邻域数据集的第一行）`scaled_data[0]`** 
的距离

```Python
        distances = sklearn.metrics.pairwise_distances( # 调用pairwise_distances函数
                scaled_data,
                scaled_data[0].reshape(1, -1), 
                # 将原始数据点（即邻域数据集的第一行）重塑为二维数组后输入
                metric=distance_metric 
    # 用于权重计算的距离度量（可以是欧几里得距离、曼哈顿距离等），来源：数据准备时输入
        ).ravel()
```
计算结果：`distances`矩阵
## 获取预测数据
将`inverse`数据输入预测函数，得到结果`yss`
```Python
yss = predict_fn(inverse)
```
为什么要输入反标准化后的矩阵`inverse`，而不是原扰动数据`data`？
回答：[[inverse与data的区别]]
### 验证预测模型输出`yss`是否符合要求

具体解析：[[lime算法中预测模型输出结果的检验代码解析]]

```Python
        if self.mode == "classification":
            if len(yss.shape) == 1:
                raise NotImplementedError("LIME does not currently support "
                                          "classifier models without probability "
                                          "scores. If this conflicts with your "
                                          "use case, please let us know: "
                                          "https://github.com/datascienceinc/lime/issues/16")
            elif len(yss.shape) == 2:
                if self.class_names is None:
                    self.class_names = [str(x) for x in range(yss[0].shape[0])]
                else:
                    self.class_names = list(self.class_names)
                if not np.allclose(yss.sum(axis=1), 1.0):
                    warnings.warn("""
                    Prediction probabilties do not sum to 1, and
                    thus does not constitute a probability space.
                    Check that you classifier outputs probabilities
                    (Not log probabilities, or actual class predictions).
                    """)
            else:
                raise ValueError("Your model outputs "
                                 "arrays with {} dimensions".format(len(yss.shape)))

        # for regression, the output should be a one-dimensional array of predictions
        else:
            try:
                if len(yss.shape) != 1 and len(yss[0].shape) == 1:
                    yss = np.array([v[0] for v in yss])
                assert isinstance(yss, np.ndarray) and len(yss.shape) == 1
            except AssertionError:
                raise ValueError("Your model needs to output single-dimensional \
                    numpyarrays, not arrays of {} dimensions".format(yss.shape))
```

### 保存`yss`的特征 
首先保存`yss`中的一些特征，用于后续处理

```Python
predicted_value = yss[0] # 获取预测数据的一些特征
min_y = min(yss)  
max_y = max(yss)  
```

### 调整`yss`维度
```Python
# add a dimension to be compatible with downstream machinery  
yss = yss[:, np.newaxis] # 增加一个维度
```
作用：统一处理流程，因为分类模型的输出是二维的（样本数 x 类别数）

## 处理特征
### 准备特征名
```Python
        feature_names = copy.deepcopy(self.feature_names) # 拷贝已有特征名
        if feature_names is None: # 若未指定，则生成一个默认特征名
            feature_names = [str(x) for x in range(data_row.shape[0])]
```

### 处理特征值（标准化位数）
将所有特征的值四舍五入为2位小数
```Python
        if sp.sparse.issparse(data_row): # 对于稀疏数据
            # convert_and_round函数：四舍五入到2位小数
            values = self.convert_and_round(data_row.data) 
            feature_indexes = data_row.indices
        else: # 对于非稀疏数据
            values = self.convert_and_round(data_row)
            feature_indexes = None
```

### 处理特征名

对于特征的名称：
- 若`categorical_names`中指定：则直接用
- 若`categorical_names`中未指定：则将其值转换为对应的类别名称
- 若指定了离散化器：则按照离散化器指定的离散化名称

**（1）若无离散化器**

其中，分类索引`categorical_features`：来源于[[inverse方法#准备索引`categorical_features`]]

```Python
        for i in self.categorical_features: # 遍历所有分类索引
            if self.discretizer is not None and i in self.discretizer.lambdas:
                continue # 若该特征已被离散化，则跳过
            name = int(data_row[i]) # 若未离散化，将其值转换为对应的类别名称
            if i in self.categorical_names: # 若指定过类别名则直接使用
                name = self.categorical_names[i][name]
            feature_names[i] = '%s=%s' % (feature_names[i], name) # 列表中更新当前特征的名称，以包括其类别名称
            values[i] = 'True'
        categorical_features = self.categorical_features
```

**（2）若有离散化器**

```Python
        discretized_feature_names = None
        if self.discretizer is not None:
	        # 存在离散化器时，使用离散化器使连续特征离散化
            categorical_features = range(data.shape[1]) # 改变索引，均置为分类特征
            discretized_instance = self.discretizer.discretize(data_row)
            discretized_feature_names = copy.deepcopy(feature_names)
            for f in self.discretizer.names: # 沿用离散化器中的特征名称
                discretized_feature_names[f] = self.discretizer.names[f][int(
                        discretized_instance[f])]
```
## 构造解释器
（1）构造处理和显示特征数据的辅助类`domain_mapper`（用于可视化）
```Python
domain_mapper = TableDomainMapper(feature_names,
                                  values,
                                  scaled_data[0],
                                  categorical_features=categorical_features,
                                  discretized_feature_names=discretized_feature_names,
                                  feature_indexes=feature_indexes)

```

（2）构造存储和展示模型局部解释的对象`ret_exp`
>同时也是整个lime算法的返回值

```Python
ret_exp = explanation.Explanation(domain_mapper,
                                  mode=self.mode,
                                  class_names=self.class_names)

```
## 生成局部解释

### 1. 初始化解释器
即对解释器Explanation类实例的属性进行初始化

根据预测模型是分类、回归分成两种不同处理方式：

（1）**若是分类模型**

```Python
        if self.mode == "classification":
            ret_exp.predict_proba = yss[0] # 预测概率设置为模型对原始数据点的预测概率
            if top_labels: # 找出预测概率最高的 `top_labels` 个类别
                labels = np.argsort(yss[0])[-top_labels:]
                ret_exp.top_labels = list(labels)
                ret_exp.top_labels.reverse()
```

（2）**若是回归模型**

```Python
        else: # 设置预测值及其取值上下限
            ret_exp.predicted_value = predicted_value 
            ret_exp.min_value = min_y 
            ret_exp.max_value = max_y
            labels = [0] # 默认只处理一个标签（因为回归模型通常只有一个连续的输出）
```

### 2. 生成解释

```Python
        for label in labels: # 遍历每个标签
            (ret_exp.intercept[label],
             ret_exp.local_exp[label],
             ret_exp.score, ret_exp.local_pred) = self.base.explain_instance_with_data( # 生成每个标签对应的局部解释
                    scaled_data,
                    yss,
                    distances,
                    label,
                    num_features,
                    model_regressor=model_regressor,
                    feature_selection=self.feature_selection)
```
explain_instance_with_data方法内部流程：详见[[explain_instance_with_data方法]]
### 3.调整格式
***仅对于回归模型***
- 处理局部解释结果，复制到不同标签下
- 将正负值进行修改，从而区分对预测值的正向和负向影响

```Python
  if self.mode == "regression":
            ret_exp.intercept[1] = ret_exp.intercept[0]
            ret_exp.local_exp[1] = [x for x in ret_exp.local_exp[0]]
            ret_exp.local_exp[0] = [(i, -1 * j) for i, j in ret_exp.local_exp[1]]
```