<?php

namespace Webplumbr\BlogBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class BlogCommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $data = isset($options['data'])
            ? $options['data']
            : array_fill_keys(
                array('commenter', 'content', 'post_id', 'ip'),
                null
            );

        $builder
            ->add('commenter', 'text', array('label' => 'Commenter', 'attr' => array('value' => $data['commenter'], 'placeholder' => 'Your name')))
            ->add('content', 'textarea', array('label' => 'Content', 'attr' => array('value' => $data['content'], 'rows' => 10, 'placeholder' => "You are entitled to your Opinion but please refrain from comments of abusive nature")))
            ->add('ip', 'hidden', array('required' => false, 'data' => $data['ip']))
            ->add('post_id', 'hidden', array('required' => false, 'data' => $data['post_id']))
            ->add('save', 'submit', array('label' => 'Save'));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $collectionConstraint = new Collection(array(
            'commenter' => array(
                new NotBlank(),
                new Length(array('min' => 2, 'max' => 128))
            ),
            'content' => array(
                new NotBlank()
            ),
            'post_id' => array(
                new Range(array('min' => 0, 'max' => 10000000))
            ),
            'ip' => array(
                new Length(array('min' => 0, 'max' => 30))
            ),
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
        return 'comment';
    }

    private function getCommentStatusKeys()
    {
        return array('approved', 'unapproved', 'spam');
    }

    private function getCommentStatusChoices()
    {
        return array_combine(
            $this->getCommentStatusKeys(),
            array('Approved', 'Unapproved', 'Spam')
        );
    }
}