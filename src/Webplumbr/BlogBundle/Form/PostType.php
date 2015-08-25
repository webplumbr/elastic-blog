<?php

namespace Webplumbr\BlogBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Range;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $data = isset($options['data'])
                ? $options['data']
                : array_fill_keys(
                    array('id', 'title', 'content', 'tags', 'comment_status', 'status', 'post_id', 'post_date'),
                    null
                );

        $builder
            ->add('title', 'text', array('label' => 'Title', 'attr' => array('value' => $data['title'])))
            ->add('content', 'textarea', array('label' => 'Content', 'attr' => array('value' => $data['content'], 'rows' => 10)))
            ->add('tags', 'text', array('label' => 'Tags', 'attr' => array('value' => $data['tags'])))
            ->add('comment_status', 'choice', array('choices' => $this->getCommentStatusChoices(), 'attr' => array('value' => $data['comment_status'])))
            ->add('status', 'choice', array('choices' => $this->getPostStatusChoices(), 'attr' => array('value' => $data['status'])))
            ->add('id', 'hidden', array('required' => false, 'data' => $data['id']))
            ->add('post_id', 'hidden', array('required' => false, 'data' => $data['post_id']))
            ->add('post_date', 'hidden', array('required' => false, 'data' => $data['post_date']))
            ->add('save', 'submit', array('label' => 'Save'));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $collectionConstraint = new Collection(array(
            'title' => array(
                new NotBlank(),
                new Length(array('min' => 2, 'max' => 128))
            ),
            'content' => array(
                new NotBlank()
            ),
            'tags' => array(
                new NotBlank(),
                new Length(array('min' => 2, 'max' => 255))
            ),
            'comment_status' => array(
                new NotBlank(),
                new Choice(array(
                    'choices' => $this->getCommentStatusKeys()
                ))
            ),
            'status' => array(
                new NotBlank(),
                new Choice(array(
                    'choices' => $this->getPostStatusKeys()
                ))
            ),
            'id' => array(
                new Length(array('min' => 1, 'max' => 40))
            ),
            'post_id' => array(
                new Range(array('min' => 1, 'max' => 10000000))
            ),
            'post_date' => array(
                new DateTime()
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
        return 'post';
    }

    private function getCommentStatusKeys()
    {
        return array('open', 'closed');
    }

    private function getPostStatusKeys()
    {
        return array('publish', 'draft');
    }

    private function getCommentStatusChoices()
    {
        return array_combine(
            $this->getCommentStatusKeys(),
            array('Open', 'Closed')
        );
    }

    private function getPostStatusChoices()
    {
        return array_combine(
            $this->getPostStatusKeys(),
            array('Publish', 'Draft')
        );
    }
}