#机器学习/向量自回归VAR
References：
- [【VAR模型 | 时间序列】帮助文档：VAR模型的引入和Python实践（含源代码）_python如何试下var回归-CSDN博客](https://blog.csdn.net/wzk4869/article/details/130479918)
- [VAR模型的构建过程、缘起发展、优点、局限及其与SVAR模型对应关系的直观解释（李子奈、潘文卿#5.4）——杨经国老师-Manueld-稍后再看-哔哩哔哩视频 (bilibili.com)](https://www.bilibili.com/list/watchlater?oid=700282436&bvid=BV1zm4y1a7FU&spm_id_from=333.1007.top_right_bar_window_view_later.content.click)
- [【VAR模型 | 时间序列】帮助文档：VAR模型的引入和Python实践（含源代码）-CSDN博客](https://blog.csdn.net/wzk4869/article/details/130479918)


VAR区别于AR：变量为V（向量）→方程自变量$y_t$ 
→$y_t$ 为k行的**列向量**  
![[240226-向量自回归（ Vector AutoRegression, VAR）模型-1.png]]


### 原理概述
- **目的**：VAR模型的目的是捕捉多个时间序列变量之间的内在动态关系。每个变量被模型化为它自己的滞后值以及其他变量的滞后值的线性函数。
- **多变量特性**：VAR模型是多变量的（multivariate），即它包含多个变量的联合动态。
- 假设变量之间的关系是**线性**的
- 需要大量的参数来估计，这可能导致模型**过度拟合**，尤其是当涉及的时间序列数量很多时
- **最大特性**：非结构化（即是优点也是缺点）→ 如何结构化：[[S-VAR]]

### 数学表述
一个基本的VAR模型可以表示为：
$$
y_t = A_1 y_{t-1} + A_2 y_{t-2} + \cdots + A_p y_{t-p} + c + \epsilon_t \\
= \sum_{i=1}^{p} A_i y_{t-i} + c + \epsilon_t
$$
其中：
- $y_t$ 是在时间 $t$ 的变量向量。→p×1的列向量 ^6c017e
- $A_i$ 是系数矩阵，表示滞后 $i$ 的影响。→ p×p的矩阵 ^0f57ab
- $p$ 是模型的滞后阶数。
- $c$ 是**常数项**（也可能包含趋势项）。→ p×1的列向量
- $\epsilon_t$ 是误差项，通常假设为**白噪声**，服从一定的分布。→ p×1的列向量
矩阵和列向量为什么是这样？见：[[VAR模型的矩阵描述]]
### 估计步骤
1. **滞后选择**：选择合适的滞后阶数 $p$ 是VAR模型建立的第一步，通常通过LR统计量、信息准则（如AIC、SC）来确定。
2. **模型估计**：每个方程看作独立的方程 → 使用[[最小二乘法OLS]]逐一估计系数矩阵 $A_i$。
3. **检验**：对估计得到的模型进行检验，包括检查残差的相关性、稳定性以及预测能力等。


