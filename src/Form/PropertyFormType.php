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

class PropertyFormType extends AbstractType
{
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
       
            ->add('title', TextType::class)
            ->add('address', TextType::class, ['required' => false])
            //->add('postalCode', TextType::class)
            //->add('city', TextType::class)
            //->add('country', TextType::class)
            ->add('firstname1', TextType::class)
            ->add('lastname1', TextType::class)
            ->add('date_duh', BirthdayType::class, ['format' => 'dd-MMM-yyyy'])
            ->add('date_chaudiere', BirthdayType::class, ['format' => 'dd-MMM-yyyy'])
            ->add('date_cheminee', BirthdayType::class, ['format' => 'dd-MMM-yyyy'])
            ->add('date_climatisation', BirthdayType::class, ['format' => 'dd-MMM-yyyy'])
            ->add('dateofbirth1', BirthdayType::class, ['format' => 'dd-MMM-yyyy'])
            ->add('firstname2', TextType::class, ['required' => false])
            ->add('lastname2', TextType::class, ['required' => false])
            ->add('dateofbirth2', BirthdayType::class, ['required' => false, 'format' => 'dd-MMM-yyyy'])
            ->add('mail1', EmailType::class, ['required' => false])
            ->add('mail2', EmailType::class, ['required' => false])
            ->add('buyer_phone1', TextType::class, ['required' => false])
            ->add('buyer_phone2', TextType::class, ['required' => false])
            ->add('goodType', ChoiceType::class, ['choices' => array_flip(Property::LIFETIME_TYPES), 'choice_translation_domain' => false])
            ->add('propertyType', TextType::class, ['required' => false])
            ->add('good_address', TextType::class, ['required' => false])
            //->add('date_signature_acte_authentique', BirthdayType::class, ['required' => false, 'format' => 'dd-MMM-yyyy'])
            ->add('coordonnees_syndic', TextType::class, ['required' => false])
            //->add('coordonnees_syndic2', TextType::class, ['required' => false,'disabled' => 'true'])
            //->add('constructionYear', IntegerType::class, ['required' => false])
            //->add('livingSpace', TextType::class, ['required' => false])
            //->add('groundSurface', TextType::class, ['required' => false])
            //->add('heatingType', ChoiceType::class, ['choices' => array_flip(Property::HEATING_TYPES), 'choice_translation_domain' => false])
            ->add('debirentier_different', CheckboxType::class, ['required' => false])
            //->add('garage', IntegerType::class, ['required' => false])
            //->add('parking', IntegerType::class, ['required' => false])
            //->add('cellar', IntegerType::class, ['required' => false])
            ->add('show_duh', CheckboxType::class, ['required' => false])
            
            //->add('revaluationIndex', TextType::class, ['required' => false])
            
           
            ->add('dosAuthenticInstrument', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y'))])
            ->add('num_mandat_gestion', TextType::class, ['required' => false])
            ->add('startDateManagement', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y') + 2)])
            ->add('endDateManagement', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y') + 25)])
            
            ->add('acte_abandon_duh_drive_id', TextareaType::class, ['required' => false])
           
            ->add('chaudiere', CheckboxType::class, ['required' => false])
            ->add('fireplace', CheckboxType::class, ['required' => false])
            ->add('climatisation_pompe_chaleur', CheckboxType::class, ['required' => false])
            ->add('assurance_habitation', CheckboxType::class, ['required' => false])
            ->add('date_assurance_habitation', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(date('Y') - 50, date('Y') + 2)])
            ->add('ref_cadastrales', TextType::class, ['required' => false])
            ->add('designation_du_bien', TextareaType::class, ['required' => false])
            ->add('codes_syndic', TextType::class, ['required' => false]);
                $builder->add('date_fin_exercice_copro', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y') + 2)]);
    
            $builder->add('nom_debirentier', TextType::class, ['required' => false])
            ->add('prenom_debirentier', TextType::class, ['required' => false])
            ->add('addresse_debirentier', TextType::class, ['required' => false])
            ->add('code_postal_debirentier', TextType::class, ['required' => false])
            ->add('pays_debirentier', TextType::class, ['required' => false])
            ->add('ville_debirentier', TextType::class, ['required' => false])
            ->add('telephone_debirentier', TextType::class, ['required' => false])
            ->add('email_debirentier', TextType::class, ['required' => false]);

        /*$builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $property = $event->getData();
            $form = $event->getForm();
            $form->add('parking', IntegerType::class, ['required' => false]);
                    // check if the Product object is "new"
                    // If no data is passed to the form, the data is "null".
                    // This should be considered a new "Product"
                    if ($property->getIntitulesIndicesInitial()==1) {//urbain
                        $form->add('initialIndex', EntityType::class, [
                            'class' => RevaluationHistory::class,
                            'query_builder' => function (EntityRepository $er) {
                                return $er->createQueryBuilder('rh')
                                ->where('rh.type LIKE :key1')
                                ->setParameter('key1', "Urbains")
                                    ->orderBy('rh.id', 'DESC');
                            },
                            'choice_label' => function (RevaluationHistory $rh): string {
                                return $rh->getValue().' '.$rh->getType().' mois de '.strftime('%B %Y',$rh->getDate()->getTimestamp());
                            },
                        ]);
                    }else if ($property->getIntitulesIndicesInitial()==2) {//ménages
                        $form->add('initialIndex', EntityType::class, [
                            'class' => RevaluationHistory::class,
                            'query_builder' => function (EntityRepository $er) {
                                return $er->createQueryBuilder('rh')
                                ->where('rh.type LIKE :key2 ')
                                
                                ->setParameter('key2', "Ménages")
                                    ->orderBy('rh.id', 'DESC');
                            },
                            'choice_label' => function (RevaluationHistory $rh): string {
                                return $rh->getValue().' '.$rh->getType().' mois de '.strftime('%B %Y',$rh->getDate()->getTimestamp());
                            },
                        ]);
                    }
        });*/
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
        ]);
    }
}
