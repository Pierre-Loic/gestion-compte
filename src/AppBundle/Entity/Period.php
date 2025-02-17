<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;

/**
 * Period
 *
 * @ORM\Table(name="period")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\PeriodRepository")
 */
class Period
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="day_of_week", type="smallint")
     */
    private $dayOfWeek;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="start", type="time")
     */
    private $start;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="end", type="time")
     */
    private $end;

    /**
     * One Period has One Job.
     * @ORM\ManyToOne(targetEntity="Job", inversedBy="periods")
     * @ORM\JoinColumn(name="job_id", referencedColumnName="id", nullable=false)
     */
    private $job;

    /**
     * One Period have Many Positions.
     * @ORM\OneToMany(targetEntity="PeriodPosition", mappedBy="period", cascade={"persist", "remove"}), orphanRemoval=true)
     */
    private $positions;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->positions = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set dayOfWeek
     *
     * @param integer $dayOfWeek
     *
     * @return Period
     */
    public function setDayOfWeek($dayOfWeek)
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    /**
     * Get dayOfWeek
     *
     * @return int
     */
    public function getDayOfWeek()
    {
        return $this->dayOfWeek;
    }

    /**
     * Get dayOfWeekString
     *
     * @return int
     */
    public function getDayOfWeekString()
    {
        // return jddayofweek($this->dayOfWeek, CAL_DOW_LONG);  # in english...
        return date('l', strtotime("Monday + {$this->dayOfWeek} days"));
    }

    /**
     * Set start
     *
     * @param \DateTime $start
     *
     * @return Period
     */
    public function setStart($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Get start
     *
     * @return \DateTime
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set end
     *
     * @param \DateTime $end
     *
     * @return Period
     */
    public function setEnd($end)
    {
        $this->end = $end;

        return $this;
    }

    /**
     * Get end
     *
     * @return \DateTime
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * Set job
     *
     * @param \AppBundle\Entity\Job $job
     *
     * @return Period
     */
    public function setJob(\AppBundle\Entity\Job $job = null)
    {
        $this->job = $job;

        return $this;
    }

    /**
     * Get job
     *
     * @return \AppBundle\Entity\Job
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * Add periodPosition
     *
     * @param \AppBundle\Entity\PeriodPosition $periodPosition
     *
     * @return Period
     */
    public function addPosition(\AppBundle\Entity\PeriodPosition $position)
    {
        $position->setPeriod($this);
        $this->positions[] = $position;

        return $this;
    }

    /**
     * Remove periodPosition
     *
     * @param \AppBundle\Entity\PeriodPosition $position
     */
    public function removePosition(\AppBundle\Entity\PeriodPosition $position)
    {
        $this->positions->removeElement($position);
    }

    /**
     * Get periodPositions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPositions()
    {
        return $this->positions;
    }

    /**
     * Get all the positions per week cycle
     *
     * @return array
     */
    public function getPositionsPerWeekCycle(): array
    {
        $positionsPerWeekCycle = array();
        foreach ($this->positions as $position) {
            if (!array_key_exists($position->getWeekCycle(), $positionsPerWeekCycle)) {
                $positionsPerWeekCycle[$position->getWeekCycle()] = array();
            }
            $positionsPerWeekCycle[$position->getWeekCycle()][] = $position;
        }
        ksort($positionsPerWeekCycle);
        return $positionsPerWeekCycle;
    }

    /**
     * Return true if at least one shifter (a.k.a. beneficiary) registered for
     * this period is "problematic", meaning with a withdrawn or frozen membership
     * of if the shifter is member of the flying team.
     *
     * useful only if the use_fly_and_fixed is activated
     *
     * @param String|null $weekFilter a string of the week to keep or null if no filter
     * @return bool
     */
    public function isProblematic(?String $weekFilter=null): bool
    {

        foreach ($this->positions as $position) {
            if($shifter = $position->getShifter()){
                if((($weekFilter && $position->getWeekCycle()==$weekFilter) or !$weekFilter)
                    and ($shifter->isFlying()
                    or $shifter->getMembership()->isFrozen()
                    or $shifter->getMembership()->isWithdrawn())){
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Return true if no shifter (a.k.a. beneficiary) are registered for the period
     *
     * useful only if the use_fly_and_fixed is activated
     *
     * @param String|null $weekFilter a string of the week to keep or null if no filter
     * @return bool
     */
    public function isEmpty(?String $weekFilter=null): bool
    {
        // false at the first position with a shifter
        foreach ($this->positions as $position) {
            if($position->getShifter()){
                if(($weekFilter && $position->getWeekCycle()==$weekFilter) or !$weekFilter){
                    return false;
                }
            }
        }
        // is empty if there are actually some position
        return count ($this->getGroupedPositionsPerWeekCycle($weekFilter)) != 0;
    }

    /**
     * Return true if all the periods have been assigned to a shifter (a.k.a. beneficiary)
     *
     * useful only if the use_fly_and_fixed is activated
     *
     * @param String|null $weekFilter a string of the week to keep or null if no filter
     * @return bool
     */
    public function isFull(?String $weekFilter=null): bool
    {
        // false at the first position without a shifter
        foreach ($this->positions as $position) {
            if(! $position->getShifter()){
                if(($weekFilter && $position->getWeekCycle()==$weekFilter) or !$weekFilter){
                    return false;
                }
            }
        }
        // is empty if there are actually some position
        return count ($this->getGroupedPositionsPerWeekCycle($weekFilter)) != 0;
    }

    /**
     * Return true if all the periods have been assigned to a shifter (a.k.a. beneficiary)
     *
     * useful only if the use_fly_and_fixed is activated
     *
     * @param String|null $weekFilter a string of the week to keep or null if no filter
     * @return bool
     */
    public function isPartial(?String $weekFilter=null): bool
    {
        // false at the first position with a shifter
        $slotEmpty = false;
        $slotTaken = false;

        foreach ($this->positions as $position) {
            if(($weekFilter && $position->getWeekCycle()==$weekFilter) or !$weekFilter){
                if($position->getShifter()){
                    $slotTaken = True;
                }else{
                    $slotEmpty = True;
                }
            }
            if ($slotTaken and $slotEmpty){
                return true;
            }
        }

        return false;
    }

    /**
     * Get periodPositions grouped per week cycle
     *
     * @param String|null $weekFilter a string of the week to keep or null if no filter
     * @return array
     */
    public function getGroupedPositionsPerWeekCycle(?String $weekFilter=null): array
    {
        $aggregatePerFormation = array();
        foreach ($this->positions as $position) {
            if (!array_key_exists($position->getWeekCycle(), $aggregatePerFormation)) {
                $aggregatePerFormation[$position->getWeekCycle()] = array();
            }
            if ($position->getFormation()) {
                $formation = $position->getFormation()->getName();
            } else {
                $formation = "Membre";
            }
            if (array_key_exists($formation, $aggregatePerFormation[$position->getWeekCycle()])) {
                $aggregatePerFormation[$position->getWeekCycle()][$formation] += 1;
            } else {
                $aggregatePerFormation[$position->getWeekCycle()][$formation] = 1;
            }
        }
        ksort($aggregatePerFormation);
        $aggregatePerWeekCycle = array();


        foreach ($aggregatePerFormation as $week => $position) {
            if($weekFilter && $week==$weekFilter or !$weekFilter){
                //week_filter not null and in the filter list or week_filter null
                $key = $week;
                foreach ($aggregatePerWeekCycle as $w => $p) {
                    if ($p == $position) {
                        $key = $w.", ".$week;
                        unset($aggregatePerWeekCycle[$w]);
                        break;
                    }
                }
                $aggregatePerWeekCycle[$key] = $position;
            }
        }

        ksort($aggregatePerWeekCycle);
        return $aggregatePerWeekCycle;
    }
}
