#机器学习/支持向量机SVM
References：
- [SVM简介-CSDN博客](https://blog.csdn.net/johnny_love_1968/article/details/116566101)

### 原理概述
- 目的：找到一个[[超平面]]，能够最大化地分隔不同类别的数据点
- 在最基础的分类上，引入核函数kernel：用于**将原始输入空间映射到新的特征空间**，使得原本线性不可分的样本在核空间可分
![[240222-支持向量机SVM-3.png]]
### 步骤
最基础→ ==线性可分SVM==
- 超平面：线性方程$\mathbf{w} \cdot \mathbf{x} + b = 0$
- 支持向量：超平面两侧最靠近的数据点 ^99fb76
- 间隔：数据点到超平面的距离，满足$\mathbf{w} \cdot \mathbf{x}_i + b \geq +-1$
- **核心：优化问题**
	- 目标函数：最大化间隔，即最小化$\mathbf{x}_i$，即$\min_{\mathbf{w}, b} \frac{1}{2} \|\mathbf{w}\|^2$
	- 约束条件：$\text{subject to } y_i(\mathbf{w} \cdot \mathbf{x}_i + b) \geq 1, \forall i$
引入松弛因子→==线性SVM==
- 松弛因子：并入目标函数，用来适当调整两侧分界线的位置
引入**核函数**→==非线性SVM==
- 核函数：将原始输入空间映射到新的特征空间，从而使得非线性（线性不可分）的数据线性可分
![[240222-支持向量机SVM-2.png]]
拓展到回归问题→==[[支持向量回归SVR]]==
