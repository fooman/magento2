<?php
/**
 * @copyright Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 */
namespace Magento\Framework\Interception\Code\Generator;

class Interceptor extends \Magento\Framework\Code\Generator\EntityAbstract
{
    /**
     * Entity type
     */
    const ENTITY_TYPE = 'interceptor';

    /**
     * @param string $modelClassName
     * @return string
     */
    protected function _getDefaultResultClassName($modelClassName)
    {
        return $modelClassName . '_' . ucfirst(static::ENTITY_TYPE);
    }

    /**
     * Returns list of properties for class generator
     *
     * @return array
     */
    protected function _getClassProperties()
    {
        return [];
    }

    /**
     * Get default constructor definition for generated class
     *
     * @return array
     */
    protected function _getDefaultConstructorDefinition()
    {
        $reflectionClass = new \ReflectionClass($this->_getSourceClassName());
        $constructor = $reflectionClass->getConstructor();
        $parameters = [];
        if ($constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                $parameters[] = $this->_getMethodParameterInfo($parameter);
            }
        }

        return [
            'name' => '__construct',
            'parameters' => array_merge(
                [
                    ['name' => 'pluginLocator', 'type' => '\Magento\Framework\ObjectManagerInterface'],
                    ['name' => 'pluginList', 'type' => '\Magento\Framework\Interception\PluginListInterface'],
                    ['name' => 'chain', 'type' => '\Magento\Framework\Interception\ChainInterface'],
                ],
                $parameters
            ),
            'body' => "\$this->pluginLocator = \$pluginLocator;\n" .
            "\$this->pluginList = \$pluginList;\n" .
            "\$this->chain = \$chain;\n" .
            "\$this->subjectType = get_parent_class(\$this);\n" .
            (count(
                $parameters
            ) ? "parent::__construct({$this->_getParameterList(
                $parameters
            )});" : '')
        ];
    }

    /**
     * Returns list of methods for class generator
     *
     * @return mixed
     */
    protected function _getClassMethods()
    {
        $methods = [$this->_getDefaultConstructorDefinition()];

        $reflectionClass = new \ReflectionClass($this->_getSourceClassName());
        $publicMethods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($publicMethods as $method) {
            if ($this->isInterceptedMethod($method)) {
                $methods[] = $this->_getMethodInfo($method);
            }
        }

        return $methods;
    }

    /**
     * Whether method is intercepted
     *
     * @param \ReflectionMethod $method
     * @return bool
     */
    protected function isInterceptedMethod(\ReflectionMethod $method)
    {
        return !($method->isConstructor() ||
            $method->isFinal() ||
            $method->isStatic() ||
            $method->isDestructor()) && !in_array(
                $method->getName(),
                ['__sleep', '__wakeup', '__clone']
            );
    }

    /**
     * Retrieve method info
     *
     * @param \ReflectionMethod $method
     * @return array
     */
    protected function _getMethodInfo(\ReflectionMethod $method)
    {
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameters[] = $this->_getMethodParameterInfo($parameter);
        }

        $methodInfo = [
            'name' => $method->getName(),
            'parameters' => $parameters,
            'body' => "\$pluginInfo = \$this->pluginList->getNext(\$this->subjectType, '{$method->getName()}');\n" .
            "if (!\$pluginInfo) {\n" .
            "    return parent::{$method->getName()}({$this->_getParameterList(
                $parameters
            )});\n" .
            "} else {\n" .
            "    return \$this->___callPlugins('{$method->getName()}', func_get_args(), \$pluginInfo);\n" .
            "}",
            'docblock' => ['shortDescription' => '{@inheritdoc}'],
        ];

        return $methodInfo;
    }

    /**
     * @param array $parameters
     * @return string
     */
    protected function _getParameterList(array $parameters)
    {
        return implode(
            ', ',
            array_map(
                function ($item) {
                    return "$" . $item['name'];
                },
                $parameters
            )
        );
    }

    /**
     * Generate resulting class source code
     *
     * @return string
     */
    protected function _generateCode()
    {
        $this->_classGenerator->setTraits(array('\Magento\Framework\Interception\Interceptor'));
        $typeName = $this->_getFullyQualifiedClassName($this->_getSourceClassName());
        $reflection = new \ReflectionClass($typeName);

        if ($reflection->isInterface()) {
            $this->_classGenerator->setImplementedInterfaces([$typeName]);
        } else {
            $this->_classGenerator->setExtendedClass($typeName);
        }
        return parent::_generateCode();
    }

    /**
     * {@inheritdoc}
     */
    protected function _validateData()
    {
        $result = parent::_validateData();

        if ($result) {
            $sourceClassName = $this->_getSourceClassName();
            $resultClassName = $this->_getResultClassName();

            if ($resultClassName !== $sourceClassName . '\\Interceptor') {
                $this->_addError(
                    'Invalid Interceptor class name [' .
                    $resultClassName .
                    ']. Use ' .
                    $sourceClassName .
                    '\\Interceptor'
                );
                $result = false;
            }
        }
        return $result;
    }
}
