
## 输入参数：
- `kernel_width`: kernel width for the [[exponential kernel]].  If None, defaults to sqrt (number of columns) * 0.75  
- `kernel`: similarity kernel that takes euclidean distances and kernel  width as input and outputs weights in (0,1). If None, defaults to an [[exponential kernel]].

## 默认：指数核函数[[exponential kernel]]

**参数** = 数据间的距离`D`

`kernel_width` = sqrt (数据的列数) * 0.75  

**返回值** = sqrt( exp( - D² / `kernel_width`²  ) )