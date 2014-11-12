<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PageBundle\Helper;

use Mautic\CoreBundle\Factory\MauticFactory;

/**
 * Class EmailTokenHelper
 */
class EmailTokenHelper
{

    /**
     * @var MauticFactory
     */
    private $factory;

    /**
     * @param MauticFactory $factory
     */
    public function __construct(MauticFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @param int $page
     *
     * @return string
     */
    public function getTokenContent($page = 1)
    {

        //set some permissions
        $permissions = $this->factory->getSecurity()->isGranted(array(
            'page:pages:viewown',
            'page:pages:viewother'
        ), "RETURN_ARRAY");

        if (!$permissions['page:pages:viewown'] && !$permissions['page:pages:viewother']) {
            return;
        }

        $session = $this->factory->getSession();

        //set limits
        $limit = 5;

        $start = ($page === 1) ? 0 : (($page-1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $request = $this->factory->getRequest();
        $search  = $request->get('search', $session->get('mautic.page.emailtoken.filter', ''));

        $session->set('mautic.page.emailtoken.filter', $search);

        $filter = array('string' => $search, 'force' => array(
            array('column' => 'p.variantParent', 'expr' => 'isNull')
        ));

        if (!$permissions['page:pages:viewother']) {
            $filter['force'][] = array('column' => 'p.createdBy', 'expr' => 'eq', 'value' => $this->factory->getUser());
        }

        $pages = $this->factory->getModel('page')->getEntities(
            array(
                'start'      => $start,
                'limit'      => $limit,
                'filter'     => $filter,
                'orderByDir' => "DESC"
            ));
        $count = count($pages);

        if ($count && $count < ($start + 1)) {
            //the number of entities are now less then the current page so redirect to the last page
            if ($count === 1) {
                $page = 1;
            } else {
                $page = (floor($limit / $count)) ? : 1;
            }
            $session->set('mautic.page.emailtoken.page', $page);
        }

        return $this->factory->getTemplating()->render('MauticPageBundle:SubscribedEvents\EmailToken:list.html.php', array(
            'items'       => $pages,
            'page'        => $page,
            'limit'       => $limit,
            'totalCount'  => $count,
            'tmpl'        => $request->get('tmpl', 'index'),
            'searchValue' => $search
        ));
    }
}
