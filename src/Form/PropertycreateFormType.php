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

class PropertycreateFormType extends AbstractType
{
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
       
            ->add('title', TextType::class)
            ->add('address', TextType::class, ['required' => false])
            ->add('postalCode', TextType::class, ['required' => false])
            ->add('city', TextType::class, ['required' => false])
            ->add('country', TextType::class, ['required' => false])
            ->add('firstname1', TextType::class, ['required' => false])
            ->add('lastname1', TextType::class, ['required' => false])
            ->add('adresse_credirentier1', TextType::class, ['required' => false])
            ->add('code_postal_credirentier1', TextType::class, ['required' => false])
            ->add('ville_credirentier1', TextType::class, ['required' => false])
            ->add('adresse_credirentier2', TextType::class, ['required' => false])
            ->add('code_postal_credirentier2', TextType::class, ['required' => false])
            ->add('ville_credirentier2', TextType::class, ['required' => false]);
            if($options['data']->getType()==2){
                $builder->add('buyerFirstname', TextType::class, ['required' => false])
                ->add('buyerLastname', TextType::class, ['required' => false])
                ->add('buyerAddress', TextType::class, ['required' => false])
                ->add('buyerPostalCode', TextType::class, ['required' => false])
                ->add('buyerCity', TextType::class, ['required' => false])
                ->add('buyerCountry', TextType::class, ['required' => false])
                ->add('buyerPhone1', TextType::class, ['required' => false])
                ->add('buyerPhone2', TextType::class, ['required' => false])
                ->add('buyerMail1', TextType::class, ['required' => false])
                ->add('buyerMail2', TextType::class, ['required' => false])
                ->add('buyer_bank_establishment_code', TextType::class, ['required' => false])
                ->add('buyer_bank_code_box', TextType::class, ['required' => false])
                ->add('buyer_bank_account_number', TextType::class, ['required' => false])
                ->add('buyer_bank_key', TextType::class, ['required' => false])
                ->add('buyer_bank_domiciliation', TextType::class, ['required' => false])
                ->add('buyer_bank_iban', TextType::class, ['required' => false])
                ->add('buyer_bank_bic', TextType::class, ['required' => false]);
            }
            $builder->add('dateofbirth1', BirthdayType::class, ['format' => 'dd-MMM-yyyy'])
            ->add('firstname2', TextType::class, ['required' => false])
            ->add('lastname2', TextType::class, ['required' => false])
            ->add('dateofbirth2', BirthdayType::class, ['required' => false, 'format' => 'dd-MMM-yyyy'])
            ->add('mail1', EmailType::class, ['required' => false])
            ->add('intitules_indices_initial', ChoiceType::class, ['choices' => array_flip(Property::intitules_indices_initial), 'choice_translation_domain' => false])
            ->add('mois_indice_initial', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy','years' => range(date("Y")-22, date("Y")) ])

            ->add('abandonmentIndex', TextType::class, ['required' => false])
            ->add('revaluationDate', DayType::class, ['required' => false])
            ->add('initialAmount', TextType::class, ['required' => false])
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
                    return $rh->getValue().' '.$rh->getType().' mois de '.(strftime('%B %Y',$rh->getDate()->getTimestamp()));
                },
                'choice_value' => 'id',
            ])
            ->add('condominiumFees', TextType::class, ['required' => false])
            ->add('garbageTax', TextType::class, ['required' => false])

             ->add('bank_establishment_code_1', TextType::class, ['required' => false])
            ->add('bank_iban_1', TextType::class, ['required' => false])
            ->add('bank_code_box_1', TextType::class, ['required' => false])
             ->add('bank_bic_1', TextType::class, ['required' => false])
            ->add('bank_rib_1', TextType::class, ['required' => false])
            ->add('bank_ics_1', TextType::class, ['required' => false,'disabled' => 'true','data' => 'FR12ZZZ886B32'],)
            ->add('bank_account_number_1', TextType::class, ['required' => false])
            ->add('bank_rum_1', TextType::class, ['required' => false])
            ->add('bank_domiciliation_1', TextType::class, ['required' => false])

          ->add('bank_establishment_code_2', TextType::class, ['required' => false])
            ->add('bank_iban_2', TextType::class, ['required' => false])
            ->add('bank_code_box_2', TextType::class, ['required' => false])
             ->add('bank_bic_2', TextType::class, ['required' => false])
            ->add('bank_rib_2', TextType::class, ['required' => false])
            ->add('bank_ics_2', TextType::class, ['required' => false,'disabled' => 'true','data' => 'FR12ZZZ886B32'],)
            ->add('bank_account_number_2', TextType::class, ['required' => false])
            ->add('bank_rum_2', TextType::class, ['required' => false])
            ->add('bank_domiciliation_2', TextType::class, ['required' => false])



            ->add('num_mandat_gestion', TextType::class, ['required' => false])
            ->add('comment', TextareaType::class, ['required' => false])
            ->add('honorary_rates_object', EntityType::class, [
                'class' => Honoraire::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('h')
                        ->orderBy('h.id', 'DESC');
                },
                'choice_label' => function (Honoraire $h): string {
                    return 'Titre: '.$h->getNom().' - Taux: '.$h->getValeur().'%';
                },
                'choice_value' => 'id',

                // 👇 IMPORTANT
                'required' => false,
                'placeholder' => '— Aucun taux —',
            ])

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
                    return $rh->getValue().' '.$rh->getType().' mois de '.(strftime('%B %Y',$rh->getDate()->getTimestamp()));
                },
                'choice_value' => 'id',
            ])
            ->add('mail2', EmailType::class, ['required' => false])

            ->add('goodType', ChoiceType::class, ['choices' => array_flip(Property::LIFETIME_TYPES), 'choice_translation_domain' => false])
            ->add('propertyType', TextType::class, ['required' => false])
            ->add('constructionYear', IntegerType::class, ['required' => false])
            ->add('livingSpace', TextType::class, ['required' => false])
            ->add('groundSurface', TextType::class, ['required' => false])
            ->add('heatingType', ChoiceType::class, ['choices' => array_flip(Property::HEATING_TYPES), 'choice_translation_domain' => false])
            ->add('garage', IntegerType::class, ['required' => false])
            ->add('parking', IntegerType::class, ['required' => false])
            ->add('cellar', IntegerType::class, ['required' => false])

            //->add('revaluationIndex', TextType::class, ['required' => false])
            ->add('fireplace', CheckboxType::class, ['required' => false])

           
            ->add('dosAuthenticInstrument', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y'))])
            ->add('startDateManagement', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y') + 2)])
            ->add('endDateManagement', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y') + 25)])
    

                        ->add('mandataire', ChoiceType::class, ['choices' => array_flip(Property::TYPES_MANDATAIRES), 'choice_translation_domain' => false])
            ->add('sell_type', ChoiceType::class, ['choices' => array_flip(Property::LIFETIME_TYPES), 'choice_translation_domain' => false])
            ->add('civilite1', ChoiceType::class, ['choices' => array_flip(Property::TYPES_CIVILITE), 'choice_translation_domain' => false])
            ->add('civilite2', ChoiceType::class, ['choices' => array_flip(Property::TYPES_CIVILITE), 'choice_translation_domain' => false])
            ->add('dernier_jour_paiement_rente', DayType::class)
            ->add('date_remise_cles', DateType::class)

            ->add('civilite_notaire', ChoiceType::class, ['choices' => array_flip(Property::TYPES_CIVILITE), 'choice_translation_domain' => false])
            ->add('nom_notaire', TextType::class, ['required' => false])
            ->add('prenom_notaire', TextType::class, ['required' => false])
            ->add('addresse_notaire', TextType::class, ['required' => false])
            ->add('code_postal_notaire', TextType::class, ['required' => false])
            ->add('ville_notaire', TextType::class, ['required' => false])
            ->add('telephone_notaire', TextType::class, ['required' => false])
            ->add('email_notaire', TextType::class, ['required' => false])

            ->add('civilite_debirentier', ChoiceType::class, ['choices' => array_flip(Property::TYPES_CIVILITE), 'choice_translation_domain' => false])
            ->add('nom_debirentier', TextType::class, ['required' => false])
            ->add('prenom_debirentier', TextType::class, ['required' => false])
            ->add('addresse_debirentier', TextType::class, ['required' => false])
            ->add('code_postal_debirentier', TextType::class, ['required' => false])
            ->add('pays_debirentier', TextType::class, ['required' => false])
            ->add('ville_debirentier', TextType::class, ['required' => false])
            ->add('telephone_debirentier', TextType::class, ['required' => false])
            ->add('email_debirentier', TextType::class, ['required' => false])

            ->add('civilite_debirentier2', ChoiceType::class, ['choices' => array_flip(Property::TYPES_CIVILITE), 'choice_translation_domain' => false])
            ->add('nom_debirentier2', TextType::class, ['required' => false])
            ->add('prenom_debirentier2', TextType::class, ['required' => false])
            ->add('addresse_debirentier2', TextType::class, ['required' => false])
            ->add('code_postal_debirentier2', TextType::class, ['required' => false])
            ->add('pays_debirentier2', TextType::class, ['required' => false])
            ->add('ville_debirentier2', TextType::class, ['required' => false])
            ->add('telephone_debirentier2', TextType::class, ['required' => false])
            ->add('email_debirentier2', TextType::class, ['required' => false]);
           

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
        ]);
    }
}
