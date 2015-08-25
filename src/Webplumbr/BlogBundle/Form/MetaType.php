<?php

namespace Webplumbr\BlogBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class MetaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $data = isset($options['data'])
            ? $options['data']
            : array_fill_keys(
                array('id', 'title', 'subtitle', 'url'),
                null
            );

        $builder
            ->add('id', 'hidden', array('data' => $data['id']))
            ->add('title', 'text', array('label' => 'Title', 'attr' => array('value' => $data['title'])))
            ->add('subtitle', 'text', array('label' => 'Subtitle', 'attr' => array('value' => $data['subtitle'])))
            ->add('url', 'text', array('label' => 'URL', 'attr' => array('value' => $data['url'])))
            ->add('save', 'submit', array('label' => 'Save'));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $collectionConstraint = new Collection(array(
            'id' => array(
                new NotBlank(),
                new Length(array('min' => 1, 'max' => 40))
            ),
            'title' => array(
                new NotBlank(),
                new Length(array('min' => 2, 'max' => 255))
            ),
            'subtitle' => array(
                new NotBlank(),
                new Length(array('min' => 2, 'max' => 255))
            ),
            'url' => array(
                new NotBlank(),
                new Length(array('min' => 2, 'max' => 1024))
            )
        ));

        $resolver->setDefaults(
            array(
                'csrf_protection'  => false,
                'constraints'      => $collectionConstraint
            )
        );
    }

    public function getName()
    {
        return 'meta';
    }
}