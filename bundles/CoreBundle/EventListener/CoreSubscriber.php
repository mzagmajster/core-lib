<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\EventListener;

use Mautic\CoreBundle\Controller\MauticController;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\MenuEvent;
use Mautic\CoreBundle\Event\RouteEvent;
use Mautic\CoreBundle\Event\IconEvent;
use Mautic\ApiBundle\Event as ApiEvents;
use Mautic\InstallBundle\Controller\InstallController;
use Mautic\UserBundle\Entity\User;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Class CoreSubscriber
 *
 * @package Mautic\CoreBundle\EventListener
 */
class CoreSubscriber extends CommonSubscriber
{

    /**
     * @return array
     */
    static public function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER          => array('onKernelController', 0),
            KernelEvents::REQUEST             => array('onKernelRequest', 0),
            CoreEvents::BUILD_MENU            => array('onBuildMenu', 9999),
            CoreEvents::BUILD_ADMIN_MENU      => array('onBuildAdminMenu', 9999),
            CoreEvents::BUILD_ROUTE           => array('onBuildRoute', 0),
            CoreEvents::FETCH_ICONS           => array('onFetchIcons', 9999),
            SecurityEvents::INTERACTIVE_LOGIN => array('onSecurityInteractiveLogin', 0)
        );
    }

    /**
     * Set default timezone/locale
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $currentUser = $this->factory->getUser();

        //set the user's timezone
        if (is_object($currentUser))
            $tz = $currentUser->getTimezone();

        if (empty($tz))
            $tz = $this->params['default_timezone'];

        date_default_timezone_set($tz);

        //set the user's default locale
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        // try to see if the locale has been set as a _locale routing parameter
        if ($locale = $request->attributes->get('_locale')) {
            $request->getSession()->set('_locale', $locale);
        } else {
            if (is_object($currentUser))
                $locale = $currentUser->getLocale();
            if (empty($locale))
                $locale = $this->params['locale'];

            // if no explicit locale has been set on this request, use one from the session
            $request->setLocale($request->getSession()->get('_locale', $locale));
        }
    }

    /**
     * Set vars on login
     *
     * @param InteractiveLoginEvent $event
     */
    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event)
    {
        $session = $event->getRequest()->getSession();
        if ($this->securityContext->isGranted('IS_AUTHENTICATED_FULLY') ||
            $this->securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $user = $event->getAuthenticationToken()->getUser();

            //set a session var for filemanager to know someone is logged in
            $session->set('mautic.user', $user->getId());

            //mark the user as last logged in
            $user = $this->factory->getUser();
            if ($user instanceof User) {
                $this->factory->getModel('user.user')->getRepository()->setLastLogin($user);
            }
        } else {
            $session->remove('mautic.user');
        }

        $session->set('mautic.basepath', $event->getRequest()->getBasePath());
    }

    /**
     * Populates namespace, bundle, controller, and action into request to be used throughout application
     *
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        //only affect Mautic controllers
        if ($controller[0] instanceof MauticController) {
            $request = $event->getRequest();

            //also set the request for easy access throughout controllers
            $controller[0]->setRequest($request);

            //set the factory for easy use access throughout the controllers
            $controller[0]->setFactory($this->factory);

            //run any initialize functions
            $controller[0]->initialize($event);
        }

        //update the user's activity marker
        if (!($controller[0] instanceof InstallController) && !defined('MAUTIC_ACTIVITY_CHECKED')) {
            //prevent multiple updates
            $user = $this->factory->getUser();
            //slight delay to prevent too many updates
            //note that doctrine will return in current timezone so we do not have to worry about that
            $delay = new \DateTime();
            $delay->setTimestamp(strtotime('2 minutes ago'));
            if ($user instanceof User && $user->getLastActive() < $delay) {
                $this->factory->getModel('user.user')->getRepository()->setLastActive($user);
            }
            define('MAUTIC_ACTIVITY_CHECKED', 1);
        }
    }

    /**
     * @param MenuEvent $event
     */
    public function onBuildMenu (MenuEvent $event)
    {
        $this->buildMenu($event, 'main');
    }

    /**
     * @param MenuEvent $event
     */
    public function onBuildAdminMenu (MenuEvent $event)
    {
        $this->buildMenu($event, 'admin');
    }

    /**
     * @param RouteEvent $event
     */
    public function onBuildRoute (RouteEvent $event)
    {
        $this->buildRoute($event, 'routing');
    }

    /**
     * @param MenuEvent $event
     */
    public function onFetchIcons (IconEvent $event)
    {
        $this->buildIcons($event);
    }
}
