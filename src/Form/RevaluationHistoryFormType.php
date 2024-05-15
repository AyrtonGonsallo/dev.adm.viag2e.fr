<?php

namespace App\Form;

use App\Entity\RevaluationHistory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevaluationHistoryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('value', TextType::class, array('label' => false))
            ->add('date', DateType::class, ['label' => false,'required' => false, 'format' => 'dd-MMM-yyyy','years' => range(date("Y")-22, date("Y")) ])
            ->add('comment', TextareaType::class, array('required' => false, 'attr' => ['class' => 'tinymce'],'label' => false));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => RevaluationHistory::class,
        ]);
    }
}
