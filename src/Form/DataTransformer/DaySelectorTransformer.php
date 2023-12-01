<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class DaySelectorTransformer implements DataTransformerInterface
{
    const DEFAULT_YEAR = 2001;

    private $fields;

    /**
     * @param array  $fields         The date fields
     */
    public function __construct(array $fields = null)
    {
        if (null === $fields) {
            $fields = ['month', 'day'];
        }

        $this->fields = $fields;
    }

    /**
     * Transforms a normalized date into a localized date.
     *
     * @param string $string dd-mm
     *
     * @return array Array date
     *
     * @throws TransformationFailedException If the given value is not a dd-mm
     */
    public function transform($string)
    {
        if (null === $string) {
            return array_intersect_key([
                'month' => '',
                'day' => '',
            ], array_flip($this->fields));
        }

        $data = explode('-', $string);
        if (count($data) !== 2) {
            throw new TransformationFailedException('Expected a dd-mm string.');
        }

        $result = array_intersect_key([
            'month' => $data[1],
            'day' => $data[0],
        ], array_flip($this->fields));

        return $result;
    }

    /**
     * @param array $value Day and month
     *
     * @return string Formatted date
     *
     * @throws TransformationFailedException If the given value is not an array,
     *                                       if the value could not be transformed
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!\is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }

        if ('' === implode('', $value)) {
            return null;
        }

        $emptyFields = [];

        foreach ($this->fields as $field) {
            if (!isset($value[$field])) {
                $emptyFields[] = $field;
            }
        }

        if (\count($emptyFields) > 0) {
            throw new TransformationFailedException(sprintf('The fields "%s" should not be empty', implode('", "', $emptyFields)));
        }

        if (isset($value['month']) && !ctype_digit((string) $value['month'])) {
            throw new TransformationFailedException('This month is invalid');
        }

        if (isset($value['day']) && !ctype_digit((string) $value['day'])) {
            throw new TransformationFailedException('This day is invalid');
        }

        if (!empty($value['month']) && !empty($value['day']) && false === checkdate($value['month'], $value['day'], self::DEFAULT_YEAR)) {
            throw new TransformationFailedException('This is an invalid date');
        }

        try {
            $string = $value['day'].'-'.$value['month'];
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        return $string;
    }
}
