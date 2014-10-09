<?php

namespace OroCRM\Bundle\MailChimpBundle\Provider\Transport;

use Doctrine\Common\Persistence\ManagerRegistry;

use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\IntegrationBundle\Provider\TransportInterface;

use OroCRM\Bundle\MailChimpBundle\Entity\Member;
use OroCRM\Bundle\MailChimpBundle\Entity\Template;
use OroCRM\Bundle\MailChimpBundle\Exception\RequiredOptionException;
use OroCRM\Bundle\MailChimpBundle\Provider\Transport\Iterator\CampaignIterator;
use OroCRM\Bundle\MailChimpBundle\Provider\Transport\Iterator\ListIterator;
use OroCRM\Bundle\MailChimpBundle\Provider\Transport\Iterator\MembersIterator;

/**
 * @link http://apidocs.mailchimp.com/api/2.0/
 * @link https://bitbucket.org/mailchimp/mailchimp-api-php/
 */
class MailChimpTransport implements TransportInterface
{
    /**#@+
     * @const string Constants related to datetime representation in MailChimp
     */
    const DATETIME_FORMAT = 'Y-m-d H:i:s';
    const DATE_FORMAT = 'Y-m-d';
    const TIME_FORMAT = 'H:i:s';
    const TIMEZONE = 'UTC';
    /**#@-*/

    /**
     * @var MailChimpClient
     */
    protected $client;

    /**
     * @var MailChimpClientFactory
     */
    protected $mailChimpClientFactory;

    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    /**
     * @param MailChimpClientFactory $mailChimpClientFactory
     * @param ManagerRegistry $managerRegistry
     */
    public function __construct(MailChimpClientFactory $mailChimpClientFactory, ManagerRegistry $managerRegistry)
    {
        $this->mailChimpClientFactory = $mailChimpClientFactory;
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function init(Transport $transportEntity)
    {
        $apiKey = $transportEntity->getSettingsBag()->get('apiKey');
        if (!$apiKey) {
            throw new RequiredOptionException('apiKey');
        }
        $this->client = $this->mailChimpClientFactory->create($apiKey);
    }

    /**
     * @link http://apidocs.mailchimp.com/api/2.0/helper/ping.php
     * @return array
     */
    public function ping()
    {
        return $this->client->ping();
    }

    /**
     * @link http://apidocs.mailchimp.com/api/2.0/campaigns/list.php
     * @return \Iterator
     */
    public function getCampaigns()
    {
        return new CampaignIterator($this->client);
    }

    /**
     * @link http://apidocs.mailchimp.com/api/2.0/lists/list.php
     * @return \Iterator
     */
    public function getLists()
    {
        return new ListIterator($this->client);
    }

    /**
     * Get all members from MailChimp that requires update.
     *
     * @link http://apidocs.mailchimp.com/export/1.0/list.func.php
     *
     * @param \DateTime|null $since
     * @return \Iterator
     */
    public function getMembersToSync(\DateTime $since = null)
    {
        $subscribersLists = $this->managerRegistry->getRepository('OroCRMMailChimpBundle:SubscribersList')
            ->getAllSubscribersListIterator();

        $parameters = ['status' => [Member::STATUS_SUBSCRIBED, Member::STATUS_UNSUBSCRIBED, Member::STATUS_CLEANED]];

        if ($since) {
            $since = clone $since;
            $since->sub(new \DateInterval('PT1S'));
            $since->setTimezone(new \DateTimeZone('UTC'));

            $parameters['since'] = $since->format(self::DATETIME_FORMAT);
        }

        return new MembersIterator($subscribersLists, $this->client, $parameters);
    }

    /**
     * Get list of MailChimp Templates.
     *
     * @link http://apidocs.mailchimp.com/api/2.0/templates/list.php
     * @return array
     */
    public function getTemplates()
    {
        return $this->client->getTemplates(
            [
                'types' => [
                    Template::TYPE_USER => true,
                    Template::TYPE_GALLERY => true,
                    Template::TYPE_BASE => true
                ],
                'filters' => [
                    'include_drag_and_drop' => true
                ]
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'orocrm.mailchimp.integration_transport.label';
    }

    /**
     * {@inheritdoc}
     */
    public function getSettingsFormType()
    {
        return 'orocrm_mailchimp_integration_transport_setting_type';
    }

    /**
     * {@inheritdoc}
     */
    public function getSettingsEntityFQCN()
    {
        return 'OroCRM\\Bundle\\MailChimpBundle\\Entity\\MailChimpTransport';
    }
}