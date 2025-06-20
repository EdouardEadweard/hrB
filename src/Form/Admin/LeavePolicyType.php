<?php

namespace App\Form\Admin;

use App\Entity\Department;
use App\Entity\LeavePolicy;
use App\Entity\LeaveType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Json;

class LeavePolicyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la politique',
                'attr' => [
                    'placeholder' => 'Ex: Politique congés payés cadres, Politique RTT...',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom de la politique est obligatoire.'
                    ]),
                    new Length([
                        'min' => 3,
                        'max' => 150,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Décrivez en détail cette politique de congés...',
                    'class' => 'form-control',
                    'rows' => 5
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'La description de la politique est obligatoire.'
                    ]),
                    new Length([
                        'min' => 10,
                        'max' => 1000,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('department', EntityType::class, [
                'label' => 'Département concerné',
                'class' => Department::class,
                'choice_label' => function (Department $department) {
                    return $department->getName() . ' (' . $department->getCode() . ')';
                },
                'placeholder' => '-- Sélectionnez un département --',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('d')
                        ->where('d.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('d.name', 'ASC');
                },
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner un département.'
                    ])
                ],
                'help' => 'Cette politique s\'appliquera uniquement aux employés de ce département.'
            ])
            ->add('leaveType', EntityType::class, [
                'label' => 'Type de congé',
                'class' => LeaveType::class,
                'choice_label' => function (LeaveType $leaveType) {
                    return $leaveType->getName() . ' (' . $leaveType->getCode() . ')';
                },
                'placeholder' => '-- Sélectionnez un type de congé --',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('lt')
                        ->where('lt.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('lt.name', 'ASC');
                },
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner un type de congé.'
                    ])
                ],
                'help' => 'Type de congé auquel cette politique s\'applique.'
            ])
            ->add('rules', TextareaType::class, [
                'label' => 'Règles (Format JSON)',
                'attr' => [
                    'placeholder' => '{"maxConsecutiveDays": 15, "minAdvanceNotice": 7, "blackoutPeriods": ["2024-12-20/2024-01-05"], "carryOverLimit": 5}',
                    'class' => 'form-control',
                    'rows' => 8,
                    'data-toggle' => 'tooltip',
                    'title' => 'Format JSON requis'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Les règles de la politique sont obligatoires.'
                    ]),
                    new Json([
                        'message' => 'Les règles doivent être au format JSON valide.'
                    ])
                ],
                'help' => 'Règles au format JSON. Exemples de clés : maxConsecutiveDays, minAdvanceNotice, blackoutPeriods, carryOverLimit, requiresManagerApproval, etc.'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Politique active',
                'required' => false,
                'data' => true,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ],
                'help' => 'Décochez pour désactiver temporairement cette politique.'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LeavePolicy::class,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}