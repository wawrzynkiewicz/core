<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass
 */
class Action extends Meta
{
    /**
     * Types of actions.
     */
    const TYPE_CAMPAIGN     = 'campaign';
    const TYPE_MILESTONE    = 'milestone';
    const TYPE_ACTIVITY     = 'activity';
    const TYPE_OPERATION    = 'operation';

    /**
     * Status constants.
     */
    const STATUS_OPEN                   = 'open';
    const STATUS_PAUSED                 = 'paused';
    const STATUS_CLOSED                 = 'closed';
    const STATUS_INTERACTION_REQUIRED   = 'interaction required';

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    protected $name;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $startDate;

    /**
     * A string defining the interval range as a relative date format with a
     * value in the future. For example, if the report operation is supposed
     * to run every hour, the interval would be "1 hour".
     *
     * Relative date formats are defined here:
     * http://php.net/manual/en/datetime.formats.relative.php
     *
     * TODO: Make sure that provided interval has a future value (not pointing
     * to the past).
     *
     * @ORM\Column(name="`interval`", type="string", length=100, nullable=true)
     */
    protected $interval;

    /**
     * The date when the Action will be run the next time. It will be
     * increased by the scheduler.
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $nextRun;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $endDate;

    /**
     * The number of times an Action is supposed to be repeated.
     *
     * @ORM\Column(type="smallint", nullable=true)
     */
    protected $endOccurrence;

    /**
     * @ORM\Column(type="string", length=20)
     */
    protected $status = self::STATUS_OPEN;

    /**
     * @ORM\ManyToOne(targetEntity="Hook")
     */
    protected $triggerHook;

    /**
     * Set name
     *
     * @param string $name
     * @return Action
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set startDate
     *
     * @param \DateTime $startDate
     * @return Action
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * Get startDate
     *
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Set interval
     *
     * @param string $interval
     * @return Action
     */
    public function setInterval($interval)
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * Get interval
     *
     * @return string
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * Set nextRun
     *
     * @param \DateTime $nextRun
     * @return Action
     */
    public function setNextRun($nextRun)
    {
        $this->nextRun = $nextRun;

        return $this;
    }

    /**
     * Get nextRun
     *
     * @return \DateTime
     */
    public function getNextRun()
    {
        return $this->nextRun;
    }

    /**
     * Set endDate
     *
     * @param \DateTime $endDate
     * @return Action
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * Get endDate
     *
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @param mixed $endOccurrence
     */
    public function setEndOccurrence($endOccurrence)
    {
        $this->endOccurrence = $endOccurrence;
    }

    /**
     * @return mixed
     */
    public function getEndOccurrence()
    {
        return $this->endOccurrence;
    }

    /**
     * Set status
     *
     * @param string $status
     * @return Action
     */
    public function setStatus($status)
    {
        if (!in_array($status, array(
            self::STATUS_OPEN,
            self::STATUS_PAUSED,
            self::STATUS_CLOSED,
            self::STATUS_INTERACTION_REQUIRED,
        ))) {
            throw new \InvalidArgumentException("Invalid status in ".get_class($this).".");
        }

        // If end date is in the past, status is automatically "closed" if status is not "paused".
        if(
        ($status != self::STATUS_CLOSED && $status != self::STATUS_PAUSED && $this->endDate && $this->endDate < new \DateTime('now')) ||
        ($status != self::STATUS_CLOSED && $status != self::STATUS_PAUSED && !$this->endDate && $this->startDate && $this->startDate < new \DateTime('now'))){
            // TODO: Warning that status is different from what has been provided.
            $status = self::STATUS_CLOSED;
        }

        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set triggerHook
     *
     * @param \CampaignChain\CoreBundle\Entity\Hook $triggerHook
     * @return Action
     */
    public function setTriggerHook(\CampaignChain\CoreBundle\Entity\Hook $triggerHook = null)
    {
        $this->triggerHook = $triggerHook;

        return $this;
    }

    /**
     * Get triggerHook
     *
     * @return \CampaignChain\CoreBundle\Entity\Hook
     */
    public function getTriggerHook()
    {
        return $this->triggerHook;
    }

    /**
     * Identifies the type of action.
     *
     * @param $action
     * @return string
     */
    public function getType(){
        $class = get_class($this);

        if(strpos($class, 'CoreBundle\Entity\Operation') !== false){
            return self::TYPE_OPERATION;
        }
        if(strpos($class, 'CoreBundle\Entity\Activity') !== false){
            return self::TYPE_ACTIVITY;
        }
        if(strpos($class, 'CoreBundle\Entity\Milestone') !== false){
            return self::TYPE_MILESTONE;
        }
        if(strpos($class, 'CoreBundle\Entity\Campaign') !== false){
            return self::TYPE_CAMPAIGN;
        }

        return false;
    }

    static function getRepositoryName($actionType)
    {
        switch($actionType){
            case self::TYPE_OPERATION:
                $repositoryName = 'CampaignChainCoreBundle:Operation';
                break;
            case self::TYPE_ACTIVITY:
                $repositoryName = 'CampaignChainCoreBundle:Activity';
                break;
            case self::TYPE_MILESTONE:
                $repositoryName = 'CampaignChainCoreBundle:Milestone';
                break;
            case self::TYPE_CAMPAIGN:
                $repositoryName = 'CampaignChainCoreBundle:Campaign';
                break;
            default:
                throw new \Exception('Action type "'.$actionType.'" does not exist.');
                break;
        }

        return $repositoryName;
    }
}
