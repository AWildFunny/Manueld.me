#机器学习/lime算法
#Python/lime库

LIME：Local Interpretable Model-agnostic Explanations "局部可解释模型-不透明的解释器"

>Local：只能预测样本的局部
>Interpretable：可被人理解的
>Model-agnostic：通用的，与模型无关的
>Explainations：解释

主要用于解释机器学习模型的预测结果，帮助理解模型是如何做出特定预测的

## lime_tabular
用于解释**表格类（矩阵）数据**的预测模型
`LimeTabularExplainer` 类的主要目的是为表格数据（如矩阵数据）生成解释。以下是该类及其关键方法 `explain_instance_with_data` 的代码拆解和工作流程的详细解释：
### 类初始化 (`__init__` 方法)
1. **参数初始化**:
   - 设置基本参数，如训练数据 (`training_data`)、模式 (`mode`)、特征名称 (`feature_names`)、分类特征 (`categorical_features`) 等。
   - 初始化随机状态 (`random_state`)。

2. **离散化处理**:
   - 如果启用连续特征的离散化 (`discretize_continuous`)，则根据提供的 `discretizer` 设置（如 'quartile'）初始化相应的离散化器。

3. **核函数设置**:
   - 设置核函数 (`kernel_fn`) 用于计算扰动数据点与原始数据点之间的相似度。见[[核函数kernel_fn配置]]

4. **特征缩放和频率统计:
   - 使用 `StandardScaler` 对训练数据进行标准化处理，这对于处理数值特征非常重要。
   - 对于分类特征，计算训练数据中每个类别的值和频率。

### 数据逆转换函数 (`__data_inverse`)
解析见[[inverse方法拆解]]
1. **数据初始化**:
   - 根据输入的数据行（`data_row`）是稀疏还是密集，初始化数据结构。

2. **扰动数据生成**:
   - 对于数值特征，通过从正态分布采样并根据训练数据的均值和标准差进行反向操作（反标准化）来扰动数据。
   - 对于分类特征，通过根据训练数据中的分布采样并生成二元特征来扰动数据。当值与原始实例相同时，二元特征为1。

3. **输出扰动数据和逆数据**:
   - 返回扰动数据（`data`）和逆数据（`inverse`）。逆数据对于分类特征而言不是二元的，而是与原始数据相同的类别值。

### 解释生成函数 (`explain_instance`)
#### 函数输入：
`explain_instance` 函数的参数具有特定的含义和作用，下面我将逐一解释这些参数：

1. **data_row**:
   - 类型：1维 numpy 数组或 scipy.sparse 矩阵。
   - 含义：代表要解释的数据实例。通常是模型预测的输入数据的一行。

2. **predict_fn**:
   - 类型：函数。
   - 含义：预测函数。对于分类器，这应该是一个接受 numpy 数组并输出预测概率的函数。对于回归器，它接受 numpy 数组并返回预测值。这个函数需要能够处理多个特征向量（从 `data_row` 扰动得到的）。

3. **labels**:
   - 类型：可迭代对象。
   - 含义：要解释的标签。在分类问题中，这表示感兴趣的特定类别的标签。

4. **top_labels**:
   - 类型：整数或 None。
   - 含义：如果不为 None，将忽略 `labels` 参数，并为预测概率最高的前 K 个标签生成解释，其中 K 是此参数的值。

5. **num_features**:
   - 类型：整数。
   - 含义：解释中要包含的最大特征数量。这决定了局部解释模型将考虑多少个特征。

6. **num_samples**:
   - 类型：整数。
   - 含义：生成的邻域数据大小，即用于学习局部线性模型的扰动样本数量。

7. **distance_metric**:
   - 类型：字符串。
   - 含义：用于权重计算的距离度量。这个度量确定了扰动样本与原始数据点之间的相似度，通常使用的是欧几里得距离。

8. **model_regressor**:
   - 类型：sklearn 回归器或 None。
   - 含义：用于解释的回归模型。如果为 None，则默认使用 `Ridge` 回归。该回归器必须具有 `coef_` 属性，且其 `fit` 方法需要接受 `sample_weight` 参数。
#### 函数步骤

1. **数据准备**:
   - 如果输入的数据行是稀疏的，则将其转换为 CSR 格式。
   - 调用 `__data_inverse` 函数生成邻域数据（扰动数据）。

2. **数据标准化**:
   - 对于密集数据，执行标准化操作。对于稀疏数据，则进行相应的处理以保持数据的稀疏性。

3. **计算距离**:
   - 使用 `pairwise_distances` 计算标准化后的邻域数据与原始实例之间的距离。

4. **获取模型预测**:
   - 使用提供的预测函数 (`predict_fn`) 对逆数据（扰动后的数据）进行预测。

5. **特征处理和映射**:
   - 对特征名称进行复制和处理，特别是对于分类特征。如果使用了离散化器，还需要处理离散化后的特征名称。

6. **生成解释**:
   - 使用 `LimeBase` 类的 `explain_instance_with_data` 方法生成解释。这包括选择特征、训练局部线性模型、计算模型分数和预测原始实例的预测值。

7. **处理分类或回归模式**:
   - 根据是分类还是回归模式，处理预测结果和生成解释。对于分类，解释可能包括预测概率和针对顶部标签的解释；对于回归，解释包括预测值及其范围。

8. **返回解释对象**:
   - 最终返回一个包含解释信息的 `Explanation` 对象，其中包含了局部解释、预测概率（如果适用）、预测值和模型分数。

总体来说，`LimeTabularExplainer` 类通过扰动原始数据实例来创建一个局部数据集，并在这个数据集上训练一个局部线性模型，以便为模型在特定实例上的预测提供可解释性。这个过程涵盖了从数据准备、特征处理、模型训练到最终解释生成的所有步骤。
## Explanation类
用于封装和可视化解释结果。
1. **类初始化 (`__init__` 方法)**:
   - 初始化方法设定了解释的基本配置，如解释的模式（分类或回归）、类名、随机状态等。
   - 为分类和回归模式设置了不同的属性，例如在分类模式下，存储类名和预测概率；在回归模式下，存储预测值及其范围。
2. **可用标签的获取 (`available_labels` 方法)**:
   - 用于返回哪些分类标签有解释。这对于分类任务特别有用，因为可能只对某些特定类别的解释感兴趣。
3. **解释转换为列表 (`as_list` 方法)**:
   - 将解释转换为一个列表，列表中每个元素是一个元组，包含特征表示和它对模型预测的贡献（权重）。
   - 这个方法对于可视化和进一步分析特别有用。
4. **解释转换为映射 (`as_map` 方法)**:
   - 返回一个从标签到特征贡献列表的映射。这对于程序化地访问和处理解释特别方便。
5. **将解释转换为 pyplot 图形 (`as_pyplot_figure` 方法)**:
   - 生成一个 matplotlib 图表，显示特征对模型预测的贡献。这是一个条形图，有助于直观地理解哪些特征对预测结果有积极或消极的影响。
6. **在 Jupyter 笔记本中显示解释 (`show_in_notebook` 方法)**:
   - 将解释以 HTML 格式显示在 Jupyter 笔记本中。这个方法是交互式探索解释的一个方便工具。
7. **将解释保存到文件 (`save_to_file` 方法)**:
   - 将解释以 HTML 格式保存到文件中。这对于生成报告或共享解释非常有用。
8. **将解释转换为 HTML (`as_html` 方法)**:
   - 生成一个完整的 HTML 页面，展示解释。这个页面可能包括条形图、预测概率图和其他视觉元素，取决于解释的模式和配置。


## lime_base
用于学习局部线性稀疏模型。

**核心功能** ：利用给定的扰动数据和距离信息来训练一个局部线性模型，并提供对单个实例预测的解释。这种方法允许用户理解一个复杂模型在特定实例附近的行为。通过不同的特征选择方法，可以控制模型的复杂度和解释的可解释性。
### 类初始化 (`__init__` 方法)
- `self.kernel_fn`: 存储一个核函数，用于将距离转换为接近度值。
- `self.verbose`: 控制是否打印局部模型的预测值。
- `self.random_state`: 确定随机状态，用于生成随机数。
### 生成 LARS 路径 (`generate_lars_path` 静态方法)
- 计算带权重数据的 LARS（最小角回归）路径。
- 返回正则化参数 (`alphas`) 和对应的系数 (`coefs`)。
### 前向选择 (`forward_selection` 方法)
- 迭代地向模型中添加特征，直到达到所需的特征数量。
- 每次迭代选择对模型分数贡献最大的特征。
### 特征选择 (`feature_selection` 方法)
- 根据指定的方法选择特征。方法包括：
  - `'none'`: 使用所有特征。
  - `'forward_selection'`: 使用前向选择方法。
  - `'highest_weights'`: 选择权重最高的特征。
  - `'lasso_path'`: 基于 LASSO 正则化路径选择特征。
  - `'auto'`: 自动选择方法，基于特征数量选择 `'forward_selection'` 或 `'highest_weights'`
### 解释实例 (`explain_instance_with_data` 方法)
- 使用扰动数据、标签和距离来生成解释。
- 首先，使用 `kernel_fn` 计算距离的权重。
- 根据选择的特征选择方法选择特征。
- 如果未提供 `model_regressor`，默认使用岭回归。
- 训练模型并计算模型分数。
- 返回解释，包括截距、特征权重、模型分数和在原始实例上的局部预测。
#### 详细解读：[[explain_instance_with_data方法]]
`explain_instance_with_data` 函数是 LIME 库中 `LimeBase` 类的一个核心方法，用于生成对特定数据实例的局部解释。
通过在扰动的邻域数据上训练一个线性模型来生成针对特定实例的解释。它考虑了每个扰动样本与原始样本的相似度（通过权重体现），并在此基础上选取重要特征，然后训练并评估一个局部线性模型，最后返回这个模型的参数和性能指标，作为对原始实例预测的解释。这个过程使得解释集中于对预测结果影响最大的特征，从而提供了对模型局部行为的清晰视角。
##### 1. 计算权重
```python
weights = self.kernel_fn(distances)
```
- 使用 `kernel_fn` 函数，将每个扰动样本与原始样本之间的距离转换成权重。这些权重反映了扰动样本与原始样本的相似度。

##### 2. 提取指定标签的数据
```python
labels_column = neighborhood_labels[:, label]
```
- 从扰动标签集中提取出与指定标签（`label`）对应的列。这些是模型对于扰动样本的预测标签。

##### 3. 特征选择
```python
used_features = self.feature_selection(neighborhood_data, labels_column, weights, num_features, feature_selection)
```
- 根据 `feature_selection` 参数选择特征。这个步骤决定了哪些特征将被包括在局部解释模型中。
- 可选择的方法包括前向选择（`'forward_selection'`）、选取权重最高的特征（`'highest_weights'`）、LASSO 路径（`'lasso_path'`）、使用所有特征（`'none'`）或自动选择（`'auto'`）。

##### 4. 模型回归
```python
if model_regressor is None:
    model_regressor = Ridge(alpha=1, fit_intercept=True, random_state=self.random_state)
easy_model = model_regressor
easy_model.fit(neighborhood_data[:, used_features], labels_column, sample_weight=weights)
```
- 如果没有指定 `model_regressor`，则默认使用岭回归。
- 使用选定的特征和计算出的权重来拟合模型（`easy_model`）。这个模型是局部解释的基础。

##### 5. 计算模型分数
```python
prediction_score = easy_model.score(neighborhood_data[:, used_features], labels_column, sample_weight=weights)
```
- 计算模型的分数（通常是 R^2 值），评估模型对于局部数据的拟合程度。

##### 6. 预测原始实例
```python
local_pred = easy_model.predict(neighborhood_data[0, used_features].reshape(1, -1))
```
- 使用局部解释模型对原始数据实例进行预测。

##### 7. 输出解释结果
```python
return (easy_model.intercept_, sorted(zip(used_features, easy_model.coef_), key=lambda x: np.abs(x[1]), reverse=True), prediction_score, local_pred)
```
- 返回一个四元组，包括：
  - 模型截距（`intercept`）。
  - 排序后的特征权重列表，每个元素是一个 `(特征id, 权重)` 的元组。
  - 模型的分数（`prediction_score`）。
  - 对原始实例的局部预测（`local_pred`）

##### 8. 打印调试信息（如果启用 verbose）
```python
if self.verbose:
    print('Intercept', easy_model.intercept_)
    print('Prediction_local', local_pred,)
    print('Right:', neighborhood_labels[0, label])
```
- 如果 `verbose` 参数被设置为 `True`，则打印出模型截距、局部预测值以及正确的标签值，以便进行调试和检查。


