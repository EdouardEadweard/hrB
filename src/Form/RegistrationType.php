<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Department;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\IsTrue;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Informations de connexion
            ->add('email', EmailType::class, [
                'label' => 'Adresse email *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'example@hrflow.com'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'L\'adresse email est obligatoire'
                    ]),
                    new Email([
                        'message' => 'Veuillez saisir une adresse email valide'
                    ])
                ]
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe doivent être identiques',
                'required' => true,
                'first_options' => [
                    'label' => 'Mot de passe *',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Minimum 8 caractères'
                    ]
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe *',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Répétez votre mot de passe'
                    ]
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le mot de passe est obligatoire'
                    ]),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères'
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                        'message' => 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre'
                    ])
                ]
            ])

            // Informations personnelles
            ->add('firstName', TextType::class, [
                'label' => 'Prénom *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre prénom'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le prénom est obligatoire'
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre nom de famille'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom est obligatoire'
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+229 XX XX XX XX'
                ],
                'constraints' => [
                    new Regex([
                        'pattern' => '/^(\+229|00229|229)?[0-9]{8}$/',
                        'message' => 'Veuillez saisir un numéro de téléphone valide (format béninois)'
                    ])
                ]
            ])

            // Informations professionnelles
            ->add('hireDate', DateType::class, [
                'label' => 'Date d\'embauche *',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'La date d\'embauche est obligatoire'
                    ])
                ]
            ])
            ->add('position', TextType::class, [
                'label' => 'Poste *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Développeur, Manager, RH...'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le poste est obligatoire'
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le poste doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le poste ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('department', EntityType::class, [
                'class' => Department::class,
                'choice_label' => 'name',
                'label' => 'Département *',
                'placeholder' => 'Sélectionnez un département',
                'attr' => [
                    'class' => 'form-select'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner un département'
                    ])
                ]
            ])
            ->add('manager', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getFirstName() . ' ' . $user->getLastName() . ' (' . $user->getPosition() . ')';
                },
                'label' => 'Manager',
                'placeholder' => 'Sélectionnez un manager (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôle *',
                'choices' => [
                    'Employé' => 'ROLE_EMPLOYEE',
                    'Manager' => 'ROLE_MANAGER',
                    'Administrateur RH' => 'ROLE_ADMIN',
                    'Super Administrateur' => 'ROLE_SUPER_ADMIN'
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => [
                    'class' => 'form-check'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner au moins un rôle'
                    ])
                ]
            ])

            // Conditions d'utilisation
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J\'accepte les conditions d\'utilisation et la politique de confidentialité *',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ],
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter les conditions d\'utilisation'
                    ])
                ]
            ])

            // Bouton de soumission
            ->add('submit', SubmitType::class, [
                'label' => 'Créer mon compte',
                'attr' => [
                    'class' => 'btn btn-success btn-lg w-100'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'user_registration',
        ]);
    }
}