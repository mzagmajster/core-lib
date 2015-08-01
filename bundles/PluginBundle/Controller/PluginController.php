<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PluginBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use Mautic\PluginBundle\Entity\Plugin;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class PluginController
 */
class PluginController extends FormController
{
    /**
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction ()
    {
        if (!$this->factory->getSecurity()->isGranted('plugin:plugins:manage')) {
            return $this->accessDenied();
        }

        /** @var \Mautic\PluginBundle\Model\PluginModel $pluginModel */
        $pluginModel  = $this->factory->getModel('plugin');
        $session      = $this->factory->getSession();
        $pluginFilter = $this->request->get('plugin', $session->get('mautic.integrations.filter', ''));

        $session->set('mautic.integrations.filter', $pluginFilter);

        /** @var \Mautic\PluginBundle\Helper\IntegrationHelper $integrationHelper */
        $integrationHelper  = $this->factory->getHelper('integration');
        $integrationObjects = $integrationHelper->getIntegrationObjects(null, null, true);
        $integrations       = array();

        foreach ($integrationObjects as $name => $object) {
            $settings            = $object->getIntegrationSettings();
            $integrations[$name] = array(
                'name'    => $object->getName(),
                'display' => $object->getDisplayName(),
                'icon'    => $integrationHelper->getIconPath($object),
                'enabled' => $settings->isPublished(),
                'plugin'  => $settings->getPlugin()->getId()
            );
        }

        //sort by name
        ksort($integrations);

        $tmpl = $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index';

        //get a list of plugins for filter
        $plugins = $pluginModel->getEntities(
            array(
                'hydration_mode' => 'hydrate_array'
            )
        );


        if (!empty($pluginFilter)) {
            foreach ($plugins as $plugin) {
                if ($plugin['id'] == $pluginFilter) {
                    $pluginName = $plugin['name'];
                    $pluginId   = $plugin['id'];
                    break;
                }
            }
        }

        return $this->delegateView(array(
            'viewParameters'  => array(
                'items'       => $integrations,
                'tmpl'        => $tmpl,
                'pluginFilter' => ($pluginFilter) ? array('id' => $pluginId, 'name' => $pluginName) : false,
                'plugins'      => $plugins
            ),
            'contentTemplate' => 'MauticPluginBundle:Integration:grid.html.php',
            'passthroughVars' => array(
                'activeLink'    => '#mautic_plugin_index',
                'mauticContent' => 'integration',
                'route'         => $this->generateUrl('mautic_plugin_index'),
            )
        ));
    }

    /**
     * @param string $name
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function configAction ($name)
    {
        if (!$this->factory->getSecurity()->isGranted('plugin:plugins:manage')) {
            return $this->accessDenied();
        }

        $authorize = $this->request->request->get('integration_details[in_auth]', false, true);

        /** @var \Mautic\PluginBundle\Helper\IntegrationHelper $integrationHelper */
        $integrationHelper  = $this->factory->getHelper('integration');
        $integrationObject  = $integrationHelper->getIntegrationObject($name);

        // Verify that the requested integration exists
        if (empty($integrationObject)) {
            throw $this->createNotFoundException($this->get('translator')->trans('mautic.core.url.error.404'));
        }

        $leadFields = $this->factory->getModel('plugin')->getLeadFields();

        /** @var \Mautic\PluginBundle\Integration\AbstractIntegration $integrationObject */
        $entity = $integrationObject->getIntegrationSettings();

        $form = $this->createForm('integration_details', $entity, array(
            'integration'        => $entity->getName(),
            'lead_fields'        => $leadFields,
            'integration_object' => $integrationObject,
            'action'             => $this->generateUrl('mautic_plugin_config', array('name' => $name))
        ));

        if ($this->request->getMethod() == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                $currentKeys            = $integrationObject->getDecryptedApiKeys($entity);
                $currentFeatureSettings = $entity->getFeatureSettings();

                if ($valid = $this->isFormValid($form)) {
                    $em          = $this->factory->getEntityManager();
                    $integration = $entity->getName();

                    //merge keys
                    $keys = $form['apiKeys']->getData();

                    //restore original keys then merge the new ones to keep the form from wiping out empty secrets
                    $mergedKeys = $integrationObject->mergeApiKeys($keys, $currentKeys, true);
                    $integrationObject->encryptAndSetApiKeys($mergedKeys, $entity);

                    if (!$authorize) {
                        $features = $entity->getSupportedFeatures();
                        if (in_array('public_profile', $features) || in_array('push_lead', $features)) {
                            //make sure now non-existent aren't saved
                            $featureSettings = $entity->getFeatureSettings();
                            $submittedFields = $this->request->request->get('integration_details[featureSettings][leadFields]', array(), true);
                            if (isset($featureSettings['leadFields'])) {
                                foreach ($featureSettings['leadFields'] as $f => $v) {
                                    if (empty($v) || !isset($submittedFields[$f])) {
                                        unset($featureSettings['leadFields'][$f]);
                                    }
                                }
                                $entity->setFeatureSettings($featureSettings);
                            }
                        }
                    } else {
                        //make sure they aren't overwritten because of API connection issues
                        $entity->setFeatureSettings($currentFeatureSettings);
                    }

                    $em->persist($entity);
                    $em->flush();

                    if ($authorize) {
                        //redirect to the oauth URL
                        /** @var \Mautic\PluginBundle\Integration\AbstractIntegration $integrationObject */
                        $oauthUrl = $integrationObject->getAuthLoginUrl();

                        return new JsonResponse(array(
                            'integration'         => $integration,
                            'authUrl'             => $oauthUrl,
                            'authorize'           => 1,
                            'popupBlockerMessage' => $this->factory->getTranslator()->trans('mautic.integration.oauth.popupblocked')
                        ));
                    }
                }
            }

            if (($cancelled || $valid) && !$authorize) {
                // Close the modal and return back to the list view
                return new JsonResponse(array(
                    'closeModal'    => 1,
                    'enabled'       => $entity->getIsPublished(),
                    'name'          => $integrationObject->getName(),
                    'mauticContent' => 'integration',
                ));
            }
        }

        $template    = $integrationObject->getFormTemplate();
        $objectTheme = $integrationObject->getFormTheme();
        $default     = 'MauticPluginBundle:FormTheme\Integration';
        $themes      = array($default);
        if (is_array($objectTheme)) {
            $themes = array_merge($themes, $objectTheme);
        } else if ($objectTheme !== $default) {
            $themes[] = $objectTheme;
        }

        return $this->delegateView(array(
            'viewParameters'  => array(
                'form'        => $this->setFormTheme($form, $template, $themes),
                'integration' => $integrationObject
            ),
            'contentTemplate' => $template,
            'passthroughVars' => array(
                'activeLink'    => '#mautic_plugin_index',
                'mauticContent' => 'integration',
                'route'         => false
            )
        ));
    }

    /**
     * Scans the addon bundles directly and loads bundles which are not registered to the database
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function reloadAction()
    {
        if (!$this->factory->getSecurity()->isGranted('plugin:plugins:manage')) {
            return $this->accessDenied();
        }

        /** @var \Mautic\PluginBundle\Model\PluginModel $model */
        $model   = $this->factory->getModel('plugin');
        $plugins = $this->factory->getParameter('plugin.bundles');
        $added   = $disabled = $updated = 0;

        $persist = array();

        $installedPlugins = $model->getEntities(
            array(
                'index' => 'bundle'
            )
        );

        /**
         * @var string $bundle
         * @var Plugin $plugin
         */
        foreach ($installedPlugins as $bundle => $plugin) {
            $persistUpdate = false;
            if (!isset($plugins[$bundle])) {
                if (!$plugin->getIsMissing()) {
                    //files are no longer found
                    $plugin->setIsMissing(true);
                    $disabled++;
                }
            } else {
                if ($plugin->getIsMissing()) {
                    //was lost but now is found
                    $plugin->setIsMissing(false);
                    $persistUpdate = true;
                }

                $file = $plugins[$bundle]['directory'].'/Config/config.php';

                //update details of the bundle
                if (file_exists($file)) {
                    /** @var array $details */
                    $details = include $file;

                    //compare versions to see if an update is necessary
                    $version = isset($details['version']) ? $details['version'] : '';
                    if (!empty($version) && version_compare($plugin->getVersion(), $version) == -1) {
                        $updated++;

                        //call the update callback
                        $callback = $plugins[$bundle]['bundleClass'];
                        $callback::onPluginUpdate($plugin, $this->factory);
                        $persistUpdate = true;
                    }

                    $plugin->setVersion($version);

                    $plugin->setName(
                        isset($details['name']) ? $details['name'] : $plugins[$bundle]['base']
                    );

                    if (isset($details['description'])) {
                        $plugin->setDescription($details['description']);
                    }

                    if (isset($details['author'])) {
                        $plugin->setAuthor($details['author']);
                    }
                }

                unset($plugins[$bundle]);
            }
            if ($persistUpdate) {
                $persist[] = $plugin;
            }
        }

        //rest are new
        foreach ($plugins as $plugin) {
            $added++;
            $entity = new Plugin();
            $entity->setBundle($plugin['bundle']);

            $file = $plugin['directory'].'/Config/config.php';

            //update details of the bundle
            if (file_exists($file)) {
                $details = include $file;

                if (isset($details['version'])) {
                    $entity->setVersion($details['version']);
                };

                $entity->setName(
                    isset($details['name']) ? $details['name'] : $plugin['base']
                );

                if (isset($details['description'])) {
                    $entity->setDescription($details['description']);
                }

                if (isset($details['author'])) {
                    $entity->setAuthor($details['author']);
                }
            }

            // Call the install callback
            $callback = $plugin['bundleClass'];


            $callback::onPluginInstall($this->factory);

            $persist[] = $entity;
        }

        if (!empty($persist)) {
            $model->saveEntities($persist);
        }

        // Alert the user to the number of additions
        $this->addFlash(
            'mautic.plugin.notice.reloaded',
            array(
                '%added%'    => $added,
                '%disabled%' => $disabled,
                '%updated%'  => $updated
            )
        );

        $viewParameters = array(
            'page' => $this->factory->getSession()->get('mautic.plugin.page')
        );

        // Refresh the index contents
        return $this->postActionRedirect(
            array(
                'returnUrl'       => $this->generateUrl('mautic_plugin_index', $viewParameters),
                'viewParameters'  => $viewParameters,
                'contentTemplate' => 'MauticPluginBundle:Plugin:index',
                'passthroughVars' => array(
                    'activeLink'    => '#mautic_plugin_index',
                    'mauticContent' => 'plugin'
                )
            )
        );
    }
}
