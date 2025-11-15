原理：[[向前逐步回归解决多重共线性]]
### 步骤
- 逐步向[[岭回归（`Ridge`）]]中添加特征`feature`
- 评估添加后的模型性能：[[Ridge线性回归中score方法评估拟合程度]]
- 若添加`feature`后`score`分数更高，即模型拟合地更好，则将该特征添加进`used_features`
- 返回`used_features`作为最终选择的特征

### 代码解析
```python
def forward_selection(self, data, labels, weights, num_features):
    clf = Ridge(alpha=0, fit_intercept=True, random_state=self.random_state) # 初始化的岭回归模型，还没有拟合相关参数
    used_features = []
    for _ in range(min(num_features, data.shape[1])): # 选择的特征数目：不超过指定特征数和总特征数
        max_ = -100000000
        best = 0
        for feature in range(data.shape[1]): # 遍历每个尚未选择的特征
            if feature in used_features:
                continue
            clf.fit(data[:, used_features + [feature]], labels, sample_weight=weights) # 使用当前已选择的特征集合+新的候选特征 来训练模型
            score = clf.score(data[:, used_features + [feature]], labels, sample_weight=weights) # 打分
            if score > max_: # 若添加该特征后，训练的模型性能更好，则将该特征添加进`used_features`
                best = feature
                max_ = score
        used_features.append(best)
    return np.array(used_features) # 返回`used_features`作为最终选择的特征
```

1. **初始化 Ridge 回归模型**:
   - 使用[[岭回归（`Ridge`）]]，其中 `alpha=0` 表示没有正则化项。
   - 这个模型用于评估不同特征组合的效果。

2. **特征选择循环**:
   - 这个循环运行 `min(num_features, data.shape[1])` 次，即选择的特征数量不会超过用户指定的特征数 `num_features` 和数据中的总特征数。
   - 每次迭代选择一个特征添加到模型中。

3. **内部循环 - 评估每个特征**:
   - 在内部循环中，遍历每个尚未选择的特征。
   - 使用当前已选择的特征集合加上新的候选特征来训练模型。
   - 使用 `clf.score` 评估这个特征集合的性能，这通常基于预测的准确度或类似度量。

4. **选择最佳特征**:
   - 在每次内部循环中，选择使模型性能提升最大的特征。
   - `max_` 变量用于存储当前找到的最高分数，`best` 存储对应的最佳特征索引。

5. **更新已用特征集合**:
   - 将在这一轮中选出的最佳特征添加到 `used_features` 列表中。

6. **返回结果**:
   - 最后返回一个包含已选特征索引的数组。

### 方法总结
`forward_selection` 是一个贪心算法，它在每一步**选择对模型性能提升最大的特征**。这种方法在特征数较少时效果很好，但随着特征数量的增加，计算成本也会增加。这种方法适用于需要精确特征选择的场景，尤其是在解释模型预测时，我们希望知道哪些特征对模型预测最为关键。