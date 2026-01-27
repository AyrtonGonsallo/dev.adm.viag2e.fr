<?php

namespace App\Form;

use App\Entity\PendingInvoice;
use App\Entity\Property;
use App\Entity\Honoraire;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

class InvoiceOTPTypeAnnuel extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('property', EntityType::class, ['class' => Property::class, 'data' => $options['property'], 'disabled' => $options['prop_locked'], 'query_builder' => function (EntityRepository $er) { return $er->createQueryBuilder('p')->orderBy('p.title', 'ASC'); }, 'choice_label' => 'title'])
            ->add('target', ChoiceType::class, ['choices' => ['Mandat' => 1, 'Bien' => 2, 'Acheteur' => 3], 'choice_translation_domain' => false])
            ->add('label', TextType::class, ['attr' => ['disabled' => $options['locked'], 'placeholder' => 'Texte affiché à côté du montant (ex: Rente Viagère)', 'value' => $options['label']], 'translation_domain' => false])
			->add('email', EmailType::class, array('attr' => array('placeholder' => 'E-mail...'),'required' => false))
 			->add('montantht', ChoiceType::class, ['choices' => ['Bien libre de toute occupation' => 15, 'Bien occupé en copropriété' => 18, 
 				'Bien occupé en copropriété sans rente' => 200, 'Bien occupé individuel' => 15, 'Bien occupé individuel sans rente' => 50, 
				'Gestion + de 10 biens' => 0, 'Indexation annuelle de la rente viagère' => 50], 'choice_translation_domain' => false])
 			

            ->add('period', TextType::class, ['attr' => ['placeholder' => 'Période de ...'], 'translation_domain' => false])
            ->add('reason', TextType::class, ['attr' => ['disabled' => $options['locked'], 'placeholder' => '[...]votre appel de fonds relatif à ...', 'value' => $options['reason']], 'translation_domain' => false])
        ;
		
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'  => PendingInvoice::class,

            'property'    => null,
            'amount'      => null,
            'montantht'      => null,
            'label'       => null,
            'reason'      => null,

            'locked'      => false,
            'prop_locked' => false,
        ]);
    }
}
