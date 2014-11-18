<?php

namespace OroCRM\Bundle\MailChimpBundle\ImportExport\Strategy;

use Akeneo\Bundle\BatchBundle\Entity\JobExecution;
use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Item\ExecutionContext;
use Akeneo\Bundle\BatchBundle\Step\StepExecutionAwareInterface;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

use Oro\Bundle\ImportExportBundle\Strategy\Import\ConfigurableAddOrReplaceStrategy;
use OroCRM\Bundle\MailChimpBundle\Entity\OriginAwareInterface;

abstract class AbstractImportStrategy extends ConfigurableAddOrReplaceStrategy implements
    StepExecutionAwareInterface,
    LoggerAwareInterface
{
    /**
     * @var StepExecution
     */
    protected $stepExecution;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param OriginAwareInterface $entity
     * @return OriginAwareInterface
     */
    protected function afterProcessEntity($entity)
    {
        $this->collectEntities($entity);

        return parent::afterProcessEntity($entity);
    }

    /**
     * @param OriginAwareInterface $entity
     */
    protected function collectEntities($entity)
    {
        $jobContext = $this->getJobContext();
        $processedEntities = (array)$jobContext->get('processed_entities');
        $processedEntities['originId'][] = $entity->getOriginId();
        $processedEntities['channel'] = $this->context->getOption('channel');
        $jobContext->put('processed_entities', $processedEntities);
    }

    /**
     * Update related entity.
     *
     * @param object|null $existingEntity
     * @param object|null $importedEntity
     * @param array|null $data
     * @return object|null
     */
    protected function updateRelatedEntity($existingEntity, $importedEntity, array $data = null)
    {
        if ($importedEntity) {
            $result = $importedEntity;
        } else {
            $result = $existingEntity;
        }

        return $this->processEntity($result, false, false, $data);
    }

    /**
     * @return ExecutionContext
     */
    protected function getJobContext()
    {
        /** @var JobExecution $jobExecution */
        $jobExecution = $this->stepExecution->getJobExecution();
        return $jobExecution->getExecutionContext();
    }
}
