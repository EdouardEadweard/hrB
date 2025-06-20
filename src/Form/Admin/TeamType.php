<?php

namespace App\Form\Admin;

use App\Entity\Team;
use App\Entity\User;
use App\Entity\Department;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TeamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'équipe',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Équipe Développement, Support Client...'
                ]
            ])
            
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Description détaillée de l\'équipe, ses missions, objectifs...'
                ]
            ])
            
            ->add('department', EntityType::class, [
                'label' => 'Département',
                'class' => Department::class,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionner un département',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('d')
                        ->where('d.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('d.name', 'ASC');
                }
            ])
            
            ->add('leader', EntityType::class, [
                'label' => 'Chef d\'équipe',
                'class' => User::class,
                'choice_label' => function (User $user) {
                    $position = $user->getPosition() ? ' (' . $user->getPosition() . ')' : '';
                    return $user->getFirstName() . ' ' . $user->getLastName() . $position;
                },
                'placeholder' => 'Sélectionner un chef d\'équipe',
                'required' => false,
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where('u.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('u.firstName', 'ASC')
                        ->addOrderBy('u.lastName', 'ASC');
                }
            ])
            
            ->add('isActive', CheckboxType::class, [
                'label' => 'Équipe active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ]
            ])
            
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ])
            
            ->add('cancel', SubmitType::class, [
                'label' => 'Annuler',
                'attr' => [
                    'class' => 'btn btn-secondary',
                    'formnovalidate' => true
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Team::class,
            'attr' => [
                'novalidate' => 'novalidate' // Désactive la validation HTML5 pour utiliser celle de Symfony
            ]
        ]);
    }
}