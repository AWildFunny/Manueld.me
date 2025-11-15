#机器学习/支持向量机SVM 

Reference：
- [多输出支持向量回归算法（Multi-output support vector regression，M-SVR) - 知乎 (zhihu.com)](https://zhuanlan.zhihu.com/p/583847604)
- 在时间序列预测上的应用（论文）：[🔤M - SVR🔤](zotero://note/u/63YGC9NH/)

M-SVR：标准SVR的泛化
- 允许在同一模型中处理多个输出变量
- 可以同时考虑多个时间序列之间的相关性

**核心思想**：为每个输出寻找一个回归器，这些回归器共同最小化一个包含所有输出误差的目标函数。

### 关键公式

目标函数：$L_p (W, b) = \frac{1}{2} \sum_{j=1}^H \| w^j \|^2 + C \sum_{i=1}^n L(u_i)$
其中，\( L(u_i) \) 是对每个数据点 \( i \) 预测误差的ε-不敏感损失函数，定义为：
$$L(u) = \left\{
  \begin{array}{ll}
  0 & \mbox{if } u < \epsilon \\
  u^2 - 2u\epsilon + \epsilon^2 & \mbox{if } u \geq \epsilon
  \end{array}
\right.$$
这是Vapnik的ε-不敏感损失函数的可微分形式。