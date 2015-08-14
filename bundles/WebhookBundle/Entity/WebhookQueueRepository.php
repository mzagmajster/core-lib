<?php
/**
 * @package     Allyde mPower Social Bundle
 * @copyright   Allyde, Inc. All rights reserved
 * @author      Allyde, Inc
 * @link        http://allyde.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\WebhookBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

class WebhookQueueRepository extends CommonRepository
{
    /*
     * Deletes all the webhook queues by ID
     *
     * @param $idList array of webhookqueue IDs
     */
    public function deleteQueuesById(array $idList)
    {
        $qb = $this->_em->getConnection()->createQueryBuilder();
        $qb->delete(MAUTIC_TABLE_PREFIX . 'webhook_queue')
            ->where(
                $qb->expr()->in('id', $idList)
            )
            ->execute();
    }

    /*
     * Gets a count of the webhook queues filtered by the webhook id
     */
    public function getQueueCountByWebhookId($id)
    {
        $qb = $this->_em->getConnection()->createQueryBuilder();
        $count = $qb->select('count(id) as webhook_count')
                 ->from(MAUTIC_TABLE_PREFIX . 'webhook_queue', $this->getTableAlias())
                 ->where('webhook_id = ' . $id)
                 ->execute()->fetch();

        return $count['webhook_count'];
    }
}