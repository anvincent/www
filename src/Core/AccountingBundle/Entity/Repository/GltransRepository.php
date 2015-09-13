<?php

namespace Core\AccountingBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * GltransRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class GltransRepository extends EntityRepository
{
	public function findnexttypeno()
	{
		$maxtypeno = $this	->getEntityManager()
							->createQueryBuilder()
							->select('MAX(a.typeno)')
							->from('CoreAccountingBundle:Gltrans', 'a')
							->getQuery()
							->getSingleScalarResult();
		$maxtypeno++;
		return $maxtypeno;
	}
	
	public function findnextcounterindex()
	{
		$maxcounterindex = $this	->getEntityManager()
		->createQueryBuilder()
		->select('MAX(a.counterindex)')
		->from('CoreAccountingBundle:Gltrans', 'a')
		->getQuery()
		->getSingleScalarResult();
		$maxcounterindex++;
		return $maxcounterindex;
	}
	
	public function findperiodsbytagnotstarted($id)
	{
		$result = $this->getEntityManager()
		->createQuery(
				'SELECT MAX(a.periodno) AS period
				FROM CoreAccountingBundle:Gltrans a
				WHERE a.tag = :id'
		)->setParameter('id', $id)
		->getResult();
		
		return $result;
	}
}