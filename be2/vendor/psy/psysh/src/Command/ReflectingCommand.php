<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\CodeCleaner\NoReturnValue;
use Psy\Context;
use Psy\ContextAware;
use Psy\Exception\ErrorException;
use Psy\Exception\RuntimeException;
use Psy\Exception\UnexpectedTargetException;
use Psy\Reflection\ReflectionClassConstant;
use Psy\Reflection\ReflectionConstant_;
use Psy\Util\Mirror;

/**
 * An abstract command with helpers for inspecting the current context.
 */
abstract class ReflectingCommand extends Command implements ContextAware
{
    const CLASS_OR_FUNC = '/^[\\\\\w]+$/';
    const CLASS_MEMBER = '/^([\\\\\w]+)::(\w+)$/';
    const CLASS_STATIC = '/^([\\\\\w]+)::\$(\w+)$/';
    const INSTANCE_MEMBER = '/^(\$\w+)(::|->)(\w+)$/';

    /**
     * Context instance (for ContextAware interface).
     *
     * @var Context
     */
    protected $context;

    /**
     * ContextAware interface.
     *
     * @param Context $context
     */
    public function setContext(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Get the target for a value.
     *
     * @throws \InvalidArgumentException when the value specified can't be resolved
     *
     * @param string $valueName Function, class, variable, constant, method or property name
     *
     * @return array (class or instance name, member name, kind)
     */
    protected function getTarget(string $valueName): array
    {
        $valueName = \trim($valueName);
        $matches = [];
        switch (true) {
            case \preg_match(self::CLASS_OR_FUNC, $valueName, $matches):
                return [$this->resolveName($matches[0], true), null, 0];

            case \preg_match(self::CLASS_MEMBER, $valueName, $matches):
                return [$this->resolveName($matches[1]), $matches[2], Mirror::CONSTANT | Mirror::METHOD];

            case \preg_match(self::CLASS_STATIC, $valueName, $matches):
                return [$this->resolveName($matches[1]), $matches[2], Mirror::STATIC_PROPERTY | Mirror::PROPERTY];

            case \preg_match(self::INSTANCE_MEMBER, $valueName, $matches):
                if ($matches[2] === '->') {
                    $kind = Mirror::METHOD | Mirror::PROPERTY;
                } else {
                    $kind = Mirror::CONSTANT | Mirror::METHOD;
                }

                return [$this->resolveObject($matches[1]), $matches[3], $kind];

            default:
                return [$this->resolveObject($valueName), null, 0];
        }
    }

    /**
     * Resolve a class or function name (with the current shell namespace).
     *
     * @throws ErrorException when `self` or `static` is used in a non-class scope
     *
     * @param string $name
     * @param bool   $includeFunctions (default: false)
     */
    protected function resolveName(string $name, bool $includeFunctions = false): string
    {
        $shell = $this->getApplication();

        // While not *technically* 100% accurate, let's treat `self` and `static` as equivalent.
        if (\in_array(\strtolower($name), ['self', 'static'])) {
            if ($boundClass = $shell->getBoundClass()) {
                return $boundClass;
            }

            if ($boundObject = $shell->getBoundObject()) {
                return \get_class($boundObject);
            }

            $msg = \sprintf('Cannot use "%s" when no class scope is active', \strtolower($name));
            throw new ErrorException($msg, 0, \E_USER_ERROR, "eval()'d code", 1);
        }

        if (\substr($name, 0, 1) === '\\') {
            return $name;
        }

        // Check $name against the current namespace and use statements.
        if (self::couldBeClassName($name)) {
            try {
                $name = $this->resolveCode($name.'::class');
            } catch (RuntimeException $e) {
                // /shrug
            }
        }

        if ($namespace = $shell->getNamespace()) {
            $fullName = $   �        o(�M   `               
    �      8������8�����8  �U �     ~G���ڗ�PĽ�`�M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ�u`�M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ��o�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�Tp�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�}X�M        P�����:  �8  �U �     ~G���ڗ�PĽ��X�M        P�����:  �8  �U �     ~G���ڗ�PĽ�Oz�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ��z�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�ƱM   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�{ƱM   �  �  `@]����:  �4  �� �     ����D3c�
1�v���M   T  \     @ �H  8  �U �     ~G���ڗ�PĽ�|0�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ��0�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�.}�M   �  �  `@]����:  �4  �� �     ����D3c�
1�v���M   D  \     @ �:  �4  �� �     ����D3c�
1�v���M   T  \     @ �p  8  �U �     ~G���ڗ�PĽ����M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ���M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�	~�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�}~�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ���M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�&�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�g�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�rg�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�ީ�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�A��M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ��M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�k�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ��r�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�=s�M   �  �  `@]����:  �4  �� �     ����D3c�
1�v����M   D  \     @ � � 8  �U �     ~G���ڗ�PĽ����M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�J��M   �  �  `@]����:  �4  �� �     ����D3c�
1�v��M   T  \     @ ���P�8  �U �     ~G���ڗ�PĽ��#�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�$�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�?�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ���M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ����M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�R��M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ���M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ���M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�Q��M   �  �  `@]����:  �4  �� �     ����D3c�
1�v�.��M   P  \     @ ���P�4  �� �     ����D3c�
1�v����M   `  \     @ �c�
18  �U �     ~G���ڗ�PĽ���M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ���M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�T�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�yT�M   �  �  `@]����:  �4  �� �     ����D3c�
1�v�Pv�M   `  \     @ ���P�8  �U �     ~G���ڗ�PĽ�ۓ�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�P��M   �  �  `@]����:  �4  �� �     ����D3c�
1�v���M   \  \     @ �c�
18  �U �     ~G���ڗ�PĽ��ѻM   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�
һM   �  �  `@]����:  �4  �� �     ����D3c�
1�v���M   D  \     @ �c�
18  �U �     ~G���ڗ�PĽ���M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�O�M   �  �  `@]����:  �4  �� �     ����D3c�
1�v�r;�M   T  \     @ �c�
18  �U �     ~G���ڗ�PĽ�"F�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ��F�M   �  �  `@]����:  �4  �� �     ����D3c�
1�v�1v�M   T  \     @ �c�
18  �U �     ~G���ڗ�PĽ�@��M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ����M   �  �  `@]����:  �4  �� �     ����D3c�
1�v�P��M   \  \     @ ���P�8  �U �     ~G���ڗ�PĽ�o��M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�߼�M   �  �  `@]����:  �4  �� �     ����D3c�
1�v��M   \  \     @ �c�
18  �U �     ~G���ڗ�PĽ���M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ�~��M   �  �  `@]����:  �������������������������������������������������   �        x��M   _              
    �      8�����8p�����4  �� �     ����D3c�
1�v���M   `  \     @ �:  �8  �U �     ~G���ڗ�PĽ��P�M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ��P�M   p  �  P������:  �4  �� �     ����D3c�
1�v����M   `  \     @ �E � 4  �� �     ����D3c�
1�v�=�M   `  \     @ �c h 4  �� �     ����D3c�
1�v�#`�M   `  \     @ �L  4  �� �     ����D3c�
1�v����M   `  \     @ �`  4  �� �     ����D3c�
1�v��ϓM   `  \     @ �:  �4  �� �     ����D3c�
1�v�~	�M   `  \     @ �:  �8  �U �     ~G���ڗ�PĽ��M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ�S��M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ����M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ���M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ�z͕M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ��͕M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ��:�M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ�;�M   p  �  P������:  �4  �� �     ����D3c�
1�v��f�M   `  \     @ �H  8  �U �     ~G���ڗ�PĽ����M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ���M   p  �  P������:  �4  �� �     ����D3c�
1�v�sԖM   `  \     @ �:  �4  �� �     ����D3c�
1�v�ZدM   D  \     @ �:  �4  �� �     ����D3c�
1�v�T^�M   D  \     @ �L  8  �U �     ~G���ڗ�PĽ��[�M   �  �  `@]����:  �8  �U �     ~G���ڗ�PĽ��M        P�����:  �8  �U �     ~G���ڗ�PĽ���M        P�����:  �8  �U �     ~G���ڗ�PĽ�醳M   �  �  `@]����:  �4  �� �     ����D3c�
1�v����M   D  \     @ �p  8  �U �     ~G���ڗ�PĽ�%B�M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ��B�M   p  �  P������:  �4  �� �     ����D3c�
1�v�I��M   D  \     @ �H  8  �U �     ~G���ڗ�PĽ�1��M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ����M   p  �  P������:  �4  �� �     ����D3c�
1�v��M   D  \     @ �L  8  �U �     ~G���ڗ�PĽ�y�M   p  �  P������:  �8  �U �     ~G���ڗ�PĽ���M   p  �  P������:  �4  �� �     ����D3c�
1�v�"\�M   T  \     @ �:  �4  �� �     ����D3c�
1�v�h��M   T  \     @ �  4  �� �     ����D3c�
1�v�丵M   `  \     @ �H  8  �U �     ~G���ڗ�PĽ��ĵM     �  P�\����:  �8  �U �     ~G���ڗ