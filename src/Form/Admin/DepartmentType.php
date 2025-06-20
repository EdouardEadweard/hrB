<?php

namespace App\Form\Admin;

use App\Entity\Department;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DepartmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du département *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Ressources Humaines, Informatique, Comptabilité...',
                    'maxlength' => 100
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le nom du département est obligatoire.'
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('code', TextType::class, [
                'label' => 'Code département *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: RH, IT, COMPTA...',
                    'maxlength' => 10,
                    'style' => 'text-transform: uppercase'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le code du département est obligatoire.'
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 10,
                        'minMessage' => 'Le code doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le code ne peut pas dépasser {{ limit }} caractères.'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[A-Z0-9_-]+$/',
                        'message' => 'Le code ne peut contenir que des lettres majuscules, chiffres, tirets et underscores.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Description des missions et responsabilités du département...',
                    'maxlength' => 1000
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('manager', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getFirstName() . ' ' . $user->getLastName() . ' (' . $user->getEmail() . ')';
                },
                'label' => 'Chef de département',
                'required' => false,
                'placeholder' => '-- Sélectionner un chef de département --',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('u.firstName', 'ASC')
                        ->addOrderBy('u.lastName', 'ASC');
                },
                'help' => 'Le chef de département pourra approuver les demandes de congés des employés de ce département.'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Département actif',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Décochez pour désactiver temporairement ce département sans le supprimer.',
                'data' => $options['data']->getId() ? $options['data']->isActive() : true
            ]);

        // Ajouter les boutons selon le contexte (création/modification)
        if ($options['is_edit']) {
            $builder
                ->add('save', SubmitType::class, [
                    'label' => 'Modifier le département',
                    'attr' => [
                        'class' => 'btn btn-primary me-2'
                    ]
                ])
                ->add('saveAndContinue', SubmitType::class, [
                    'label' => 'Modifier et continuer',
                    'attr' => [
                        'class' => 'btn btn-secondary me-2'
                    ]
                ]);
        } else {
            $builder
                ->add('save', SubmitType::class, [
                    'label' => 'Créer le département',
                    'attr' => [
                        'class' => 'btn btn-primary me-2'
                    ]
                ])
                ->add('saveAndAdd', SubmitType::class, [
                    'label' => 'Créer et ajouter un autre',
                    'attr' => [
                        'class' => 'btn btn-secondary me-2'
                    ]
                ]);
        }

        $builder->add('cancel', SubmitType::class, [
            'label' => 'Annuler',
            'attr' => [
                'class' => 'btn btn-outline-secondary',
                'formnovalidate' => 'formnovalidate'
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Department::class,
            'is_edit' => false,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'department';
    }
}