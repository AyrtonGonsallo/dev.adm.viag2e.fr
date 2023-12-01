<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Type;

use App\Form\DataTransformer\DaySelectorTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DayType extends AbstractType
{
    const DEFAULT_FORMAT = \IntlDateFormatter::MEDIUM;
    const HTML5_FORMAT = 'dd-MM';

    private static $widgets = [
        'text' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
        'choice' => 'Symfony\Component\Form\Extension\Core\Type\ChoiceType',
    ];

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $dateFormat = \is_int($options['format']) ? $options['format'] : self::DEFAULT_FORMAT;
        $timeFormat = \IntlDateFormatter::NONE;
        $calendar = \IntlDateFormatter::GREGORIAN;
        $pattern = \is_string($options['format']) ? $options['format'] : null;


        if ('single_text' === $options['widget']) {
            if (null !== $pattern && false === strpos($pattern, 'M') && false === strpos($pattern, 'd')) {
                throw new InvalidOptionsException(sprintf('The "format" option should contain the letters "M" or "d". Its current value is "%s".', $pattern));
            }

            $builder->addViewTransformer(new DateTimeToLocalizedStringTransformer(
                $options['model_timezone'],
                $options['view_timezone'],
                $dateFormat,
                $timeFormat,
                $calendar,
                $pattern
            ));
        } else {
            if (null !== $pattern && (false === strpos($pattern, 'M') || false === strpos($pattern, 'd'))) {
                throw new InvalidOptionsException(sprintf('The "format" option should contain the letters "M" and "d". Its current value is "%s".', $pattern));
            }

            $yearOptions = $monthOptions = $dayOptions = [
                'error_bubbling' => true,
                'empty_data' => '',
            ];
            // when the form is compound the entries of the array are ignored in favor of children data
            // so we need to handle the cascade setting here
            $emptyData = $builder->getEmptyData() ?: [];

            if (isset($emptyData['month'])) {
                $monthOptions['empty_data'] = $emptyData['month'];
            }
            if (isset($emptyData['day'])) {
                $dayOptions['empty_data'] = $emptyData['day'];
            }

            if (isset($options['invalid_message'])) {
                $dayOptions['invalid_message'] = $options['invalid_message'];
                $monthOptions['invalid_message'] = $options['invalid_message'];
                $yearOptions['invalid_message'] = $options['invalid_message'];
            }

            if (isset($options['invalid_message_parameters'])) {
                $dayOptions['invalid_message_parameters'] = $options['invalid_message_parameters'];
                $monthOptions['invalid_message_parameters'] = $options['invalid_message_parameters'];
                $yearOptions['invalid_message_parameters'] = $options['invalid_message_parameters'];
            }

            $formatter = new \IntlDateFormatter(
                \Locale::getDefault(),
                $dateFormat,
                $timeFormat,
                // see https://bugs.php.net/bug.php?id=66323
                class_exists('IntlTimeZone', false) ? \IntlTimeZone::createDefault() : null,
                $calendar,
                $pattern
            );

            // new \IntlDateFormatter may return null instead of false in case of failure, see https://bugs.php.net/bug.php?id=66323
            if (!$formatter) {
                throw new InvalidOptionsException(intl_get_error_message(), intl_get_error_code());
            }

            $formatter->setLenient(false);

            if ('choice' === $options['widget']) {
                // Only pass a subset of the options to children
                $monthOptions['choices'] = $this->formatTimestamps($formatter, '/[M|L]+/', $this->listMonths($options['months']));
                $monthOptions['placeholder'] = $options['placeholder']['month'];
                $monthOptions['choice_translation_domain'] = $options['choice_translation_domain']['month'];
                $dayOptions['choices'] = $this->formatTimestamps($formatter, '/d+/', $this->listDays($options['days']));
                $dayOptions['placeholder'] = $options['placeholder']['day'];
                $dayOptions['choice_translation_domain'] = $options['choice_translation_domain']['day'];
            }

            // Append generic carry-along options
            foreach (['required', 'translation_domain'] as $passOpt) {
                $monthOptions[$passOpt] = $dayOptions[$passOpt] = $options[$passOpt];
            }

            $builder
                ->add('month', self::$widgets[$options['widget']], $monthOptions)
                ->add('day', self::$widgets[$options['widget']], $dayOptions)
                ->addViewTransformer(new DaySelectorTransformer(
                    ['month', 'day']
                ))
                ->setAttribute('formatter', $formatter)
            ;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['widget'] = $options['widget'];

        // Change the input to a HTML5 date input if
        //  * the widget is set to "single_text"
        //  * the format matches the one expected by HTML5
        //  * the html5 is set to true
        if ($options['html5'] && 'single_text' === $options['widget'] && self::HTML5_FORMAT === $options['format']) {
            $view->vars['type'] = 'text';
        }

        if ($form->getConfig()->hasAttribute('formatter')) {
            $pattern = $form->getConfig()->getAttribute('formatter')->getPattern();

            // remove special characters unless the format was explicitly specified
            if (!\is_string($options['format'])) {
                // remove quoted strings first
                $pattern = preg_replace('/\'[^\']+\'/', '', $pattern);

                // remove remaining special chars
                $pattern = preg_replace('/[^Md]+/', '', $pattern);
            }

            // set right order with respect to locale (e.g.: de_DE=dd.MM.yy; en_US=M/d/yy)
            // lookup various formats at http://userguide.icu-project.org/formatparse/datetime
            if (preg_match('/^([Md]+)[^Md]*([Md]+)[^Md]*([Md]+)$/', $pattern)) {
                $pattern = preg_replace(['/M+/', '/d+/'], ['{{ month }}', '{{ day }}'], $pattern);
            } else {
                // default fallback
                $pattern = '{{ day }}{{ month }}';
            }

            $view->vars['date_pattern'] = $pattern;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $compound = function (Options $options) {
            return 'single_text' !== $options['widget'];
        };

        $placeholderDefault = function (Options $options) {
            return $options['required'] ? null : '';
        };

        $placeholderNormalizer = function (Options $options, $placeholder) use ($placeholderDefault) {
            if (\is_array($placeholder)) {
                $default = $placeholderDefault($options);

                return array_merge(
                    ['month' => $default, 'day' => $default],
                    $placeholder
                );
            }

            return [
                'month' => $placeholder,
                'day' => $placeholder,
            ];
        };

        $choiceTranslationDomainNormalizer = function (Options $options, $choiceTranslationDomain) {
            if (\is_array($choiceTranslationDomain)) {
                $default = false;

                return array_replace(
                    ['month' => $default, 'day' => $default],
                    $choiceTranslationDomain
                );
            }

            return [
                'month' => $choiceTranslationDomain,
                'day' => $choiceTranslationDomain,
            ];
        };

        $format = function (Options $options) {
            return 'single_text' === $options['widget'] ? self::HTML5_FORMAT : self::DEFAULT_FORMAT;
        };

        $resolver->setDefaults([
            'months' => range(1, 12),
            'days' => range(1, 31),
            'widget' => 'choice',
            'input' => 'string',
            'format' => $format,
            'model_timezone' => null,
            'view_timezone' => null,
            'placeholder' => $placeholderDefault,
            'html5' => true,
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            'by_reference' => false,
            'error_bubbling' => false,
            // If initialized with a \DateTime object, FormType initializes
            // this option to "\DateTime". Since the internal, normalized
            // representation is not \DateTime, but an array, we need to unset
            // this option.
            'data_class' => null,
            'compound' => $compound,
            'empty_data' => function (Options $options) {
                return $options['compound'] ? [] : '';
            },
            'choice_translation_domain' => false,
        ]);

        $resolver->setNormalizer('placeholder', $placeholderNormalizer);
        $resolver->setNormalizer('choice_translation_domain', $choiceTranslationDomainNormalizer);

        $resolver->setAllowedValues('input', [
            'datetime',
            'datetime_immutable',
            'string',
            'timestamp',
            'array',
        ]);
        $resolver->setAllowedValues('widget', [
            'single_text',
            'text',
            'choice',
        ]);

        $resolver->setAllowedTypes('format', ['int', 'string']);
        $resolver->setAllowedTypes('months', 'array');
        $resolver->setAllowedTypes('days', 'array');
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'day';
    }

    private function formatTimestamps(\IntlDateFormatter $formatter, $regex, array $timestamps)
    {
        $pattern = $formatter->getPattern();
        $timezone = $formatter->getTimeZoneId();
        $formattedTimestamps = [];

        $formatter->setTimeZone('UTC');

        if (preg_match($regex, $pattern, $matches)) {
            $formatter->setPattern($matches[0]);

            foreach ($timestamps as $timestamp => $choice) {
                $formattedTimestamps[$formatter->format($timestamp)] = $choice;
            }

            // I'd like to clone the formatter above, but then we get a
            // segmentation fault, so let's restore the old state instead
            $formatter->setPattern($pattern);
        }

        $formatter->setTimeZone($timezone);

        return $formattedTimestamps;
    }

    private function listMonths(array $months)
    {
        $result = [];

        foreach ($months as $month) {
            $result[gmmktime(0, 0, 0, $month, 15)] = $month;
        }

        return $result;
    }

    private function listDays(array $days)
    {
        $result = [];

        foreach ($days as $day) {
            $result[gmmktime(0, 0, 0, 5, $day)] = $day;
        }

        return $result;
    }
}
