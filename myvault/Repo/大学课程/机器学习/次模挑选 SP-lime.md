#机器学习/lime算法 

References: [黑盒模型事后归因解析：LIME方法_AI_索信达控股_InfoQ精选文章](https://www.infoq.cn/article/tuqsj0wmgakoaupwsxvy)

# 什么是SP-lime？
S：Submodular（次模：*边际效应递减*）
P：Pick
- **目的**：利用多个样本的lime局部解释(Local Explanation)，对整个模型进行**全局解释**(Global Explanation)
- **核心**：