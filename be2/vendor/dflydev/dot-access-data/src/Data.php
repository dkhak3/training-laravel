<?php

declare(strict_types=1);

/*
 * This file is a part of dflydev/dot-access-data.
 *
 * (c) Dragonfly Development Inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dflydev\DotAccessData;

use ArrayAccess;
use Dflydev\DotAccessData\Exception\DataException;
use Dflydev\DotAccessData\Exception\InvalidPathException;
use Dflydev\DotAccessData\Exception\MissingPathException;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Data implements DataInterface, ArrayAccess
{
    private const DELIMITERS = ['.', '/'];

    /**
     * Internal representation of data data
     *
     * @var array<string, mixed>
     */
    protected $data;

    /**
     * Constructor
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function append(string $key, $value = null): void
    {
        $currentValue =& $this->data;
        $keyPath = self::keyToPathArray($key);

        $endKey = array_pop($keyPath);
        foreach ($keyPath as $currentKey) {
            if (! isset($currentValue[$currentKey])) {
                $currentValue[$currentKey] = [];
            }
            $currentValue =& $currentValue[$currentKey];
        }

        if (!isset($currentValue[$endKey])) {
            $currentValue[$endKey] = [];
        }

        if (!is_array($currentValue[$endKey])) {
            // Promote this key to an array.
            // TODO: Is this really what we want to do?
            $currentValue[$endKey] = [$currentValue[$endKey]];
        }

        $currentValue[$endKey][] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value = null): void
    {
        $currentValue =& $this->data;
        $keyPath = self::keyToPathArray($key);

        $endKey = array_pop($keyPath);
        foreach ($keyPath as $currentKey) {
            if (!isset($currentValue[$currentKey])) {
                $currentValue[$currentKey] = [];
            }
            if (!is_array($currentValue[$currentKey])) {
                throw new DataException(sprintf('Key path "%s" within "%s" cannot be indexed into (is not an array)', $currentKey, self::formatPath($key)));
            }
            $currentValue =& $currentValue[$currentKey];
        }
        $currentValue[$endKey] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        $currentValue =& $this->data;
        $keyPath = self::keyToPathArray($key);

        $endKey = array_pop($keyPath);
        foreach ($keyPath as $currentKey) {
            if (!isset($currentValue[$currentKey])) {
                return;
            }
            $currentValue =& $currentValue[$currentKey];
        }
        unset($currentValue[$endKey]);
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-mutation-free
     */
    public function get(string $key, $default = null)
    {
        /** @psalm-suppress ImpureFunctionCall */
        $hasDefault = \func_num_args() > 1;

        $currentValue = $this->data;
        $keyPath = self::keyToPathArray($key);

        foreach ($keyPath as $currentKey) {
            if (!is_array($currentValue) || !array_key_exists($currentKey, $currentValue)) {
                if ($hasDefault) {
                    return $default;
                }

                throw new MissingPathException($key, sprintf('No data exists at the given path: "%s"', self::formatPath($keyPath)));
            }

            $currentValue = $currentValue[$currentKey];
        }

        return $currentValue === null ? $default : $currentValue;
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-mutation-free
     */
    public function has(string $key): bool
    {
        $currentValue = $this->data;

        foreach (self::keyToPathArray($key) as $currentKey) {
            if (
                !is_array($currentValue) ||
                !array_key_exists($currentE�    �U�;���   �E�]+��+���]���~%�N�@�|���L��u�VyWSR�t� ���{�N;�uI�F�E�   ��t�	�U�[��QR�İ �U�F��t�N�I��QRP�:�  ���^�]���N�@�F��W�    �QSQ�P�x�t� ����U�����   �E+E���~*�~�@�|�� �L��u�y�Y�;]u�:_�A[��]ËN;�uC�^�   ��t�<	�E���RP�İ �F��t�N�I��QSP肄  ���U��~��~�F�@���}@�    �y�Q�_[��]��������U��QS�]�V�u����3�W��  #L��U����tC��    94�u,�U�[+ھ@   ˃�r.�;u������U��]�u@#L����u�_^���[��]ËD�_^[��]���������������U�싁  SV�u����3�#W�|����t?94�u(�A�u�+ƻ@   ǃ�rT�<0;>u������u��  B#�|����u���  �}�4Ћ�  �|��������  �D
�΃����_^[]������������U��E�MSVW�8�E�SRP�����������u_^3�[]ËM�E+�+ǃ�����;�s��P��@S�]�L@Q�̱ ����t�I ;}$v�T��M:T�uO@Nu�M�U �9_�2^��@[]��������U������   @;�r�;�v�W�<	�M�G���    ��ERP�N�İ ��  3���t'������    ��  ��    ��  �L�@;�r�h   �Fj P��  ����@r.�@   ��$    �U�O�Q�D:��P���P���(�����@��;�v�_]������������U��8  蓁  �� 3ŉE��ESV�u�������E������������ǅ����    ;�v��PRQ�̱ �؃���������w;�tǅ����    �������   ���~%�W�@�|�� �L��u�Q�q�uuӉQ�y�O;�uR�G�������   ��t�4	�������v��QR�İ �������G��t�O�I��QRP�ۀ  ���w�u��G�@�G���     �@    �X���������+˃�@��  �}@��  �������������]RP��������������������������0������U����؉�����;��D  ��������������˃��   ���������  ��������uL;�sB�������1�L1@+��i�  ��+؋˃��   ���������  ������F��t�������������������R�U������P�E������Q������RVPQ������R���z����� ��������u2�������A@;Es��T@+��i�  ��+�A�������C  ��������������+���   ����������������~4�W�@�|���L��u#������q�OVPQ�t� ����������   �O;�u[�W�������   ��t�	�������@��QR�������İ �������G��t�O�I��QRP�~  ���������W��O�@���    �W�J������V�H�p�GRP�t� ����������M��������;�r�ȋ�����Q�������Q�R�ȱ ����tP���*  �����+���)�������������������������������������~7�W�@�|�� �L������u �Q�I�;�u������������Q�~   �O;�uU�W�������   ��t�4	�������v��PQ�İ �������G��t�O�I��QRP�i}  ���w��������G�������@�G���     �p�H���������@;Uw����������������؋�����;����������������������RP�E�
������PS�Ƌ������UQR��������������M���^3�[������]�����U��R�UR�P�������]�����������U���V���E� ����   W�}��E�    �E�    ��S��I �;�s&�H��t;1s�Y�X�A���x t2�G���@��v&�H��t;1v�Y�X�A���x t
�B�Ћ@묋X�Z�P�H�W�U�}���x[;0sB��t>�z u�r�1�B�U_�^��]Ë	�q���~ u����x�9�H�N�P�p�U�_^��]�jV�İ �     �p�@    �������VW���3�;�s�w;��4�s