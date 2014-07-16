<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Entity;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * LeadListRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class LeadListRepository extends CommonRepository
{

    /**
     * {@inheritdoc}
     *
     * @param int $id
     * @return mixed|null
     */
    public function getEntity($id = 0)
    {
        try {
            $entity = $this
                ->createQueryBuilder('l')
                ->where('l.id = :listId')
                ->setParameter('listId', $id)
                ->getQuery()
                ->getSingleResult();
        } catch (\Exception $e) {
            $entity = null;
        }
        return $entity;
    }

    /**
     * {@inheritdoc}
     *
     * @param array      $args
     * @param Translator $translator
     * @return Paginator
     */
    public function getEntities($args = array())
    {
        $q = $this
            ->createQueryBuilder('l');

        if (!$this->buildClauses($q, $args)) {
            return array();
        }

        $query = $q->getQuery();
        $result = new Paginator($query);
        return $result;
    }

    /**
     * Get a list of lists
     * @param object|boolean $user
     * @param string $alias
     * @param int    $id
     * @param int    $limit
     * @param int    $start
     */
    public function getUserSmartLists($user = false, $alias = '', $id = '')
    {
        $q = $this->_em->createQueryBuilder()
            ->select('l.name, l.id, l.alias')
            ->from('MauticLeadBundle:LeadList', 'l');
        $q->where($q->expr()->eq('l.isPublished', true));

        if (!empty($user)) {
            $q->andWhere($q->expr()->eq('l.isGlobal', true));

            $q->orWhere('l.createdBy = :user');
            $q->setParameter('user', $user);
        }

        if (!empty($alias)) {
            $q->andWhere('l.alias = :alias');
            $q->setParameter('alias', $alias);
        }

        if (!empty($id)) {
            $q->andWhere(
                $q->expr()->neq('l.id', $id)
            );
        }

        $q->orderBy('l.name');

        $results = $q->getQuery()->getArrayResult();
        return $results;
    }

    /**
     * @param QueryBuilder $q
     * @param              $filter
     * @return array
     */
    protected function addCatchAllWhereClause(QueryBuilder &$q, $filter)
    {
        $unique  = $this->generateRandomParameterName(); //ensure that the string has a unique parameter identifier
        $string  = ($filter->strict) ? $filter->string : "%{$filter->string}%";

        $expr = $q->expr()->orX(
            $q->expr()->like('l.name',  ':'.$unique),
            $q->expr()->like('l.alias', ':'.$unique)
        );
        if ($filter->not) {
            $expr = $q->expr()->not($expr);
        }
        return array(
            $expr,
            array("$unique" => $string)
        );
    }

    /**
     * @param QueryBuilder $q
     * @param              $filter
     * @return array
     */
    protected function addSearchCommandWhereClause(QueryBuilder &$q, $filter)
    {
        $command         = $field = $filter->command;
        $string          = $filter->string;
        $unique          = $this->generateRandomParameterName();
        $returnParameter = true; //returning a parameter that is not used will lead to a Doctrine error
        $expr            = false;

        switch ($command) {
            case $this->translator->trans('mautic.core.searchcommand.is'):
                switch($string) {
                    case $this->translator->trans('mautic.core.searchcommand.ismine'):
                        $expr = $q->expr()->eq("l.createdBy", $this->currentUser->getId());
                        break;
                    case $this->translator->trans('mautic.lead.list.searchcommand.isglobal'):
                        $expr = $q->expr()->eq("l.isGlobal", 1);
                        break;
                    case $this->translator->trans('mautic.core.searchcommand.ispublished'):
                        $expr = $q->expr()->eq("l.isPublished", 1);
                        break;
                    case $this->translator->trans('mautic.core.searchcommand.isunpublished'):
                        $expr = $q->expr()->eq("l.isPublished", 0);
                        break;
                }
                $returnParameter = false;
                break;
            case $this->translator->trans('mautic.core.searchcommand.name'):
                $expr = $q->expr()->like('l.name', ':'.$unique);
                break;
        }

        $string  = ($filter->strict) ? $filter->string : "%{$filter->string}%";
        if ($expr && $filter->not) {
            $expr = $q->expr()->not($expr);
        }
        return array(
            $expr,
            ($returnParameter) ? array("$unique" => $string) : array()
        );

    }

    /**
     * @return array
     */
    public function getSearchCommands()
    {
        return array(
            'mautic.core.searchcommand.is' => array(
                'mautic.lead.list.searchcommand.isglobal',
                'mautic.core.searchcommand.ismine',
                'mautic.core.searchcommand.ispublished',
                'mautic.core.searchcommand.isinactive'
            ),
            'mautic.core.searchcommand.name'
        );
    }

    /**
     * @return string
     */
    protected function getDefaultOrder()
    {
        return array(
            array('l.name', 'ASC')
        );
    }
}
