<?php

namespace App\Form;

use App\Entity\Mailing;
use App\Entity\Warrant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
class MailingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('target', ChoiceType::class, ['required' => false,'choices' => array_flip(Mailing::TYPES), 'choice_translation_domain' => false])
            ->add('object', TextType::class)
            ->add('type_envoi', ChoiceType::class, ['choices' => array_flip(Mailing::TYPES_ENVOI), 'choice_translation_domain' => false])
            ->add('single_target_email', TextType::class, ['required' => false])
            ->add('content', TextareaType::class)
            ->add('piece_jointe_drive_id', FileType::class, ['required' => false])
            ->add('single_target_id', EntityType::class, ['required' => false,
                'class' => Warrant::class,
                
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('w')
                        ->orderBy('w.id', 'DESC');
                },
                'choice_label' => function (Warrant $rh): string {
                    return $rh->getFirstname().' '.$rh->getLastname();
                },
                'choice_value' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Mailing::class,
        ]);
    }
}
