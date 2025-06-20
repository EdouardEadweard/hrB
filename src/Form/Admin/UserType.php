<?php

namespace App\Form\Admin;

use App\Entity\Department;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => [
                    'placeholder' => 'nom.prenom@entreprise.com',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'L\'adresse e-mail est obligatoire.'
                    ]),
                    new Email([
                        'message' => 'Veuillez saisir une adresse e-mail valide.'
                    ])
                ]
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'Jean',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le prénom est obligatoire.'
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Dupont',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom est obligatoire.'
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => '+229 XX XX XX XX',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Regex([
                        'pattern' => '/^[\+]?[0-9\s\-\(\)]{8,15}$/',
                        'message' => 'Veuillez saisir un numéro de téléphone valide.'
                    ])
                ]
            ])
            ->add('position', TextType::class, [
                'label' => 'Poste',
                'attr' => [
                    'placeholder' => 'Développeur, Comptable, Manager...',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le poste est obligatoire.'
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le poste doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le poste ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('hireDate', DateType::class, [
                'label' => 'Date d\'embauche',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'La date d\'embauche est obligatoire.'
                    ])
                ]
            ])
            ->add('department', EntityType::class, [
                'label' => 'Département',
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
                ]
            ])
            ->add('manager', EntityType::class, [
                'label' => 'Manager',
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getFirstName() . ' ' . $user->getLastName() . ' (' . $user->getPosition() . ')';
                },
                'placeholder' => '-- Sélectionnez un manager (optionnel) --',
                'required' => false,
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.isActive = :active')
                        ->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
                        ->setParameter('active', true)
                        ->setParameter('role', '"ROLE_MANAGER"')
                        ->orderBy('u.firstName', 'ASC')
                        ->addOrderBy('u.lastName', 'ASC');
                },
                'help' => 'Laissez vide si cet utilisateur n\'a pas de manager direct.'
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => [
                    'Employé' => 'ROLE_USER',
                    'Manager' => 'ROLE_MANAGER',
                    'Responsable RH' => 'ROLE_HR',
                    'Administrateur' => 'ROLE_ADMIN'
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ],
                'data' => ['ROLE_USER'], // Par défaut, tout utilisateur a le rôle USER
                'help' => 'Sélectionnez un ou plusieurs rôles. ROLE_USER est attribué automatiquement.'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Utilisateur actif',
                'required' => false,
                'data' => true,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ],
                'help' => 'Décochez pour désactiver temporairement cet utilisateur.'
            ]);

        // Ajout conditionnel du champ mot de passe pour les nouveaux utilisateurs
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $user = $event->getData();
            $form = $event->getForm();

            // Si c'est un nouvel utilisateur (pas d'ID), on ajoute le champ plainPassword
            if (!$user || null === $user->getId()) {
                $form->add('plainPassword', PasswordType::class, [
                    'label' => 'Mot de passe',
                    'mapped' => false, // IMPORTANT: Ne pas mapper à l'entité
                    'attr' => [
                        'placeholder' => 'Minimum 8 caractères',
                        'class' => 'form-control'
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Le mot de passe est obligatoire.'
                        ]),
                        new Length([
                            'min' => 8,
                            'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.'
                        ])
                    ],
                    'help' => 'Le mot de passe doit contenir au moins 8 caractères.'
                ]);
            } else {
                // Pour la modification, rendre le mot de passe optionnel
                $form->add('plainPassword', PasswordType::class, [
                    'label' => 'Nouveau mot de passe',
                    'mapped' => false, // IMPORTANT: Ne pas mapper à l'entité
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Laissez vide pour conserver l\'ancien mot de passe',
                        'class' => 'form-control'
                    ],
                    'constraints' => [
                        new Length([
                            'min' => 8,
                            'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.'
                        ])
                    ],
                    'help' => 'Laissez vide pour conserver le mot de passe actuel.'
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}