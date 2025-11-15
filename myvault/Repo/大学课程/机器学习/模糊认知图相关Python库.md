- ==模糊认知图：[[fcmpy]]，[[py-fcm]]==
- [[pythonfcm]]：[[模糊聚类FCM]]
- pyfcm：Firebase Cloud Messaging（FCM）的Python客户端，用于发送推送通知

# 1. fcmpy
References:
- **PyPI库说明**：[fcmpy · PyPI](https://pypi.org/project/fcmpy/)
- **库使用手册**：[fcmpy API documentation (maxiuw.github.io)](https://maxiuw.github.io/fcmpyhtml/)
- **GitHub项目地址**：[SamvelMK/FCMpy: Fuzzy Cognitive Maps for Behavior Change Interventions and Evaluation (github.com)](https://github.com/SamvelMK/FCMpy)
- **论文原文**：Mkhitaryan, Samvel, Philippe J. Giabbanelli, Maciej K. Wozniak, Gonzalo Napoles, Nanne K. de Vries和Rik Crutzen. 《FCMpy: A Python Module for Constructing and Analyzing Fuzzy Cognitive Maps》. _PeerJ Computer Science_ 8 (2022年9月23日): e1078. [https://doi.org/10.7717/peerj-cs.1078](https://doi.org/10.7717/peerj-cs.1078).

==需要  Python-3.8  版本虚拟环境安装==

>"FCMPY IS PYTHON PACKAGE FOR AUTOMATICALLY GENERATING CAUSAL WEIGHTS FOR FUZZY COGNITIVE MAPS BASED ON QUALITATIVE INPUTS (BY USING FUZZY LOGIC), OPTIMIZING THE FCM CONNECTION MATRIX VIA MACHINE LEARNING ALGORITHMS AND TESTING _WHAT-IF_ SCENARIOS."
>
>FCMPY 是一个 Python 包，用于基于定性输入（通过使用模糊逻辑）自动生成模糊认知图的因果权重，通过机器学习算法优化 FCM 连接矩阵并测试假设场景。

---

### 子模块功能
- ExpertFcm：包括**基于定性数据**导出 FCM **因果权重**的方法（基于专家输入构建 FCM，包括从定性描述中生成模糊认知图的因果权重，这些描述可以是专家对系统行为的描述或其他形式的定性数据）
- Simulation：  提供在**给定 FCM 结构**之上**运行模拟**的方法
- Intervention：  允许在指定的 FCM 之上**测试**假设场景（不同的干预措施）。

### 流程示例

##### 使用模糊逻辑构建FCM
1.生成模糊成员函数
- 创建`ExpertFcm`实例，用于处理定性的输入。
- 使用自动生成模糊函数（`automf`）方法，基于上述定义创建模糊成员函数。
2.基于定性输入数据构建FCM
- 从**CSV文件**读取由专家提供数据，表示不同概念之间的关系和影响强度。
- 计算评分的熵值
- 使用Larsen的蕴含法、家族最大聚合法和质心去模糊化法，基于定性输入数据构建FCM连接矩阵。
##### 运行FCM模拟
- 实例化`FcmSimulator`类
- 定义FCM结构，即概念间的连接矩阵和它们的权重（权重表示概念间的影响强度）
- 设置模拟开始时每个概念的状态（初始状态向量）
- 运行模拟，使用sigmoid转移函数和修改后的Kosko推理方法，设置迭代次数和停止条件（当绝对差异小于某一阈值时停止）。
##### 测试FCM上的干预
- 创建`FcmIntervention`实例。
- 使用模拟的基线状态（即未进行干预时的状态）初始化干预测试环境。
- 定义并添加多个干预措施，用来模拟对这些概念的正面或负面影响。
- 使用`test_intervention`方法测试每个干预措施，观察它们对FCM状态的影响。
- 分析每种干预措施下的理想平衡状态，与基线状态进行比较，来理解干预的效果。
### 安装配置
>we tested our library on Python 3.8. Some of the dependencies (e.g. Tensorflow) may not work properly if you use higher version of Python. Therefore, we encourage users to create a virtual environment (e.g. Conda env) with Python 3.8 and then _pip install_ our package.
1. 配置虚拟环境:`C:\Python38\python.exe -m venv env_for_fcmpy`
2. 激活虚拟环境:`env_for_fcmpy\Scripts\activate`
3. pip install安装
4. 安装时发生了环境冲突，如何解决？



# 2. py_fcm
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




