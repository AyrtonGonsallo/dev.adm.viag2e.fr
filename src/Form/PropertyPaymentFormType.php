<?php

namespace App\Form;
use App\Entity\RevaluationHistory;
use App\Entity\Property;
use App\Entity\Warrant;
use App\Entity\Honoraire;
use App\Form\Type\DayType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
setlocale(LC_TIME, 'fr_FR.UTF8', 'fr.UTF8', 'fr_FR.UTF-8', 'fr.UTF-8');

class PropertyPaymentFormType extends AbstractType
{
    
    

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        //$p=$options['data'];
        $builder
        ->add('valeur_indice_ref_og2_i_object', EntityType::class, ['required' => false,
            'class' => RevaluationHistory::class,
            'query_builder' => function (EntityRepository $er) use($options){
                if($options['data']->valeur_indice_ref_og2_i_object){
                    return $er->createQueryBuilder('rh')
                ->where('rh.id = :id')
                ->setParameter('id', $options['data']->valeur_indice_ref_og2_i_object)
                    ->orderBy('rh.id', 'DESC');
                }else{
                    return $er->createQueryBuilder('rh')
                    ->where('rh.type LIKE :key')
                    ->setParameter('key', "OGI")
                        ->orderBy('rh.id', 'DESC');
                }
               
            },
            'choice_label' => function (RevaluationHistory $rh): string {
                return $rh->getValue().' mois de '.strftime('%B %Y',$rh->getDate()->getTimestamp());
            },
            'choice_value' => 'id',
        ])
            
            ->add('intitules_indices_initial', ChoiceType::class, ['choices' => array_flip(Property::intitules_indices_initial), 'choice_translation_domain' => false])
            ->add('annuitiesDisabled', CheckboxType::class, ['required' => false])
            ->add('honorariesDisabled', CheckboxType::class, ['required' => false])
            ->add('billingDisabled', CheckboxType::class, ['required' => false])
            ->add('clause_OG2I', CheckboxType::class, ['required' => false])
            ->add('mois_indice_ref_og2_i', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', ])

            //->add('revaluationIndex', TextType::class, ['required' => false])
            ->add('initial_index_object', EntityType::class, [
                'required' => false,
                'class' => RevaluationHistory::class,
                'query_builder' => function (EntityRepository $er)  use($options){
                    if($options['data']->initial_index_object){
                        return $er->createQueryBuilder('rh')
                        ->where('rh.id = :id')
                        ->setParameter('id', $options['data']->initial_index_object)
                            ->orderBy('rh.id', 'DESC');
                    }else{
                        return $er->createQueryBuilder('rh')
                        ->where('rh.type LIKE :key1 or rh.type LIKE :key2')
                        ->setParameter('key1', "Urbains")
                        ->setParameter('key2', "Ménages")
                            ->orderBy('rh.id', 'DESC');
                    }
                    
                },
                'choice_label' => function (RevaluationHistory $rh): string {
                    return $rh->getValue().' '.$rh->getType().' mois de '.strftime('%B %Y',$rh->getDate()->getTimestamp());
                },
                'choice_value' => 'id',
            ])
            ->add('valeur_indice_reference_object', EntityType::class, [
                'required' => false,
                'class' => RevaluationHistory::class,
                'query_builder' => function (EntityRepository $er)  use($options){
                    
                        return $er->createQueryBuilder('rh')
                        ->where('rh.type LIKE :key1 or rh.type LIKE :key2 or rh.type LIKE :key3')
                        ->setParameter('key1', "Urbains")
                        ->setParameter('key2', "Ménages")
                        ->setParameter('key3', "OGI")
                        ->orderBy('rh.type', 'ASC')
                        ->addOrderBy('rh.date', 'DESC');
                    
                    
                },
                'choice_attr' => function($package) use ($options) {
                    if($options['data']->valeur_indice_reference_object){
                        $selected = false;
                        if($package == $options['data']->valeur_indice_reference_object) {
                            $selected = true;
                        }
                        return ['selected' => $selected];
                    }else{
                        return ['selected' => false];
                    }
                    
                },
                'choice_label' => function (RevaluationHistory $rh): string {
                    return $rh->getValue().' '.$rh->getType().' mois de '.strftime('%B %Y',$rh->getDate()->getTimestamp());
                },
                'choice_value' => 'id',
            ])
            ->add('abandonmentIndex', TextType::class, ['required' => false])
            ->add('revaluationDate', DayType::class)
            ->add('mois_indice_initial', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy','years' => range(date("Y")-12, date("Y")) ])
            ->add('initialAmount', TextType::class)
            ->add('honorary_rates_object',  EntityType::class, [
                'class' => Honoraire::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('h')
                        ->orderBy('h.id', 'DESC');
                },
                'choice_label' => function (Honoraire $h): string {
                    return 'Titre: '.$h->getNom().'- Taux:'.$h->getValeur().'%';
                },
                'choice_value' => 'id',
            ])
            //->add('valeur_indexation_normale', TextType::class)
            //->add('valeur_indice_ref_og2_i', TextType::class)
            ->add('plafonnement_index_og2_i', TextType::class, ['required' => false])
            ->add('condominiumFees', TextType::class, ['required' => false])
            ->add('garbageTax', TextType::class, ['required' => false])
            ->add('bank_establishment_code', TextType::class, ['required' => false])
            ->add('bank_code_box', TextType::class, ['required' => false])
            ->add('bank_account_number', TextType::class, ['required' => false])
            ->add('bank_key', TextType::class, ['required' => false])
            ->add('bank_domiciliation', TextType::class, ['required' => false])
            ->add('bank_iban', TextType::class, ['required' => false])
            ->add('bank_bic', TextType::class, ['required' => false])
            ->add('bank_ics', TextType::class, ['required' => false,'disabled' => 'true'],)
            ->add('bank_rum', TextType::class, ['required' => false])
            ->add('commentaire_honoraire', TextareaType::class, ['required' => false])
            ->add('hide_export_monthly', CheckboxType::class, ['required' => false])
            ->add('hide_export_otp', CheckboxType::class, ['required' => false])
            ->add('hide_honorary_export', CheckboxType::class, ['required' => false])
            ->add('hide_export_quarterly', CheckboxType::class, ['required' => false])
            ;

        
        
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
        ]);
    }
}
