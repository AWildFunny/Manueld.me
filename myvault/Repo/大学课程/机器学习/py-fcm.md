 #机器学习/模糊认知图 #Python/py_fcm库
 

References:
- **PyPI库说明**：[py-fcm · PyPI](https://pypi.org/project/py-fcm/)
- **GitHub项目地址**：[J41R0/PyFCM: Fuzzy cognitive maps python library (github.com)](https://github.com/J41R0/PyFCM)
- **论文原文**：[Data Drive Fuzzy Cognitive Map for Classification Problems | SpringerLink](https://link.springer.com/chapter/10.1007/978-3-030-89691-1_25)


>提供了从JSON格式读取FCM配置、运行推理、并获取最终状态的功能。它也允许通过`FcmEstimator`类来使用FCM进行数据分类，可以适用于机器学习中的分类问题

---
### 功能
- 通过 **JSON 格式**配置 FCM
- 从数据中自动生成 FCM 的拓扑结构。这包括识别系统中的概念（节点）以及这些概念之间的关系（边）。
- 使用 FCM 进行数据分类和推理，根据历史数据对新实例进行分类。

### 流程示例
##### 根据预定义的FCM进行推理
- 从JSON导入FCM的配置
- 定义概念及概念之间的关系
- 使用`run_inference`方法执行FCM推理过程，然后通过`get_final_state`获取最终概念状态。
##### 基于实际数据生成FCM模型，并进行预测
- 数据准备：使用pandas创建一个数据字典，然后转换为DataFrame
- 训练数据分割：将数据分为特征集（`x_train`）和标签集（`y_train`）。
- 模型初始化和训练：初始化`FcmEstimator`，使用`fit`方法根据训练数据训练FCM模型。
- 预测和评估：使用训练好的模型对训练集进行预测，然后计算准确率（`score`方法）
- 使用`to_json`方法导出训练好的FCM模型为JSON格式。
