<?php

namespace Webplumbr\BlogBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class NewUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $data = isset($options['data'])
            ? $options['data']
            : array_fill_keys(
                array('email', 'display_name', 'user_name', 'password', 'status'),
                null
            );

        $builder
            ->add('user_name', 'text', array('label' => 'User name', 'attr' => array('value' => $data['user_name'])))
            ->add('display_name', 'text', array('label' => 'Display name', 'attr' => array('value' => $data['display_name'])))
            ->add('email', 'text', array('label' => 'Email', 'attr' => array('value' => $data['email'])))
            ->add('password', 'password', array('label' => 'Password', 'attr' => array('value' => $data['password'])))
            ->add('status', 'choice', array('choices' => $this->getUserStatusChoices(), 'attr' => array('value' => $data['status'])))
            ->add('save', 'submit', array('label' => 'Create'));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $collectionConstraint = new Collection(array(
            'user_name' => array(
                new NotBlank(),
                new Length(array('min' => 2, 'max' => 128))
            ),
            'display_name' => array(
                new NotBlank(),
                new Length(array('min' => 2, 'max' => 128))
            ),
            'email' => array(
                new NotBlank(),
                new Length(array('min' => 2, 'max' => 255))
            ),
            'password' => array(
                new NotBlank(),
                new Length(array('min' => 8, 'max' => 60))
            ),
            'status' => array(
                new NotBlank(),
                new Choice(array(
                    'choices' => $this->getUserStatusKeys()
                ))
            )
        ));

        $resolver->setDefaults(
            array(
                'csrf_protection' => false,
                'constraints'     => $collectionConstraint
            )
        );
    }

    public function getName()
    {
        return 'user';
    }

    private function getUserStatusKeys()
    {
        return array('active', 'inactive');
    }

    private function getUserStatusChoices()
    {
        return array_combine(
            $this->getUserStatusKeys(),
            array('Active', 'Inactive')
        );
    }
}