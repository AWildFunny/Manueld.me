
**原理**: 使用 LASSO 模型的正则化路径选择特征。
**代码解析**:
   - 计算加权数据的 LASSO 路径。
   - `_, coefs = self.generate_lars_path(weighted_data, weighted_labels)`: 使用 LARS 算法计算 LASSO 路径。
   - 从 LASSO 路径的最后开始，向前迭代，直到找到一个模型，其中非零系数的数量不超过 `num_features`。
   - 选择这些非零系数对应的特征。

```Python
        elif method == 'lasso_path':
            weighted_data = ((data - np.average(data, axis=0, weights=weights))
                             * np.sqrt(weights[:, np.newaxis]))
            weighted_labels = ((labels - np.average(labels, weights=weights))
                               * np.sqrt(weights))
            nonzero = range(weighted_data.shape[1])
            _, coefs = self.generate_lars_path(weighted_data,
                                               weighted_labels)
            for i in range(len(coefs.T) - 1, 0, -1):
                nonzero = coefs.T[i].nonzero()[0]
                if len(nonzero) <= num_features:
                    break
            used_features = nonzero
            return used_features
```