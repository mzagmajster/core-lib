<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Form\Type;

use Mautic\EmailBundle\Entity\Email;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

trait DynamicContentTrait
{
    protected function addDynamicContentField(FormBuilderInterface $builder)
    {
        $builder->add(
            'dynamicContent',
            CollectionType::class,
            [
                'entry_type'         => DynamicContentFilterType::class,
                'allow_add'          => true,
                'allow_delete'       => true,
                'label'              => false,
                'entry_options'      => [
                    'label' => false,
                ],
            ]
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) {
                $data = $event->getData();
                /** @var Email $entity */
                $entity = $event->getForm()->getData();

                if (empty($data['dynamicContent'])) {
                    $data['dynamicContent'] = $entity->getDefaultDynamicContent();
                    unset($data['dynamicContent'][0]['filters']['filter']);
                }

                foreach ($data['dynamicContent'] as $key => $dc) {
                    if (empty($dc['filters'])) {
                        $data['dynamicContent'][$key]['filters'] = $entity->getDefaultDynamicContent()[0]['filters'];
                    }
                }

                $event->setData($data);
            }
        );
    }
}
