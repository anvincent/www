<?php
// src/Core/AccountingBundle/Controller/PageController.php

namespace Core\AccountingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

// add entities
use Core\AccountingBundle\Entity\Gltrans;
use Core\AccountingBundle\Entity\Journal;
use Core\AccountingBundle\Entity\Periods;
use Core\AccountingBundle\Entity\Chartmaster;
use Core\AccountingBundle\Entity\Chartdetails;
use Core\AccountingBundle\Entity\Tags;
use Core\AccountingBundle\Entity\Document;

// add forms
use Core\AccountingBundle\Form\GltransType;
use Core\AccountingBundle\Form\JournalsType;
use Core\AccountingBundle\Form\DocumentType;


class TransactionsController extends Controller
{
	/* Main */
	public function indexAction()
	{
		return $this->render('CoreAccountingBundle:Transactions:index.html.twig');
	}	
	
	/* Manual Journal Entries - gltrans 
	 * 		add
	 * 		get
	 * 		delete
	 */
	public function addManualTransactionAction($typeno)
	{
		$em = $this	->getDoctrine()
					->getManager();
		if ($typeno == 0) {
			// new transaction typeno, get typeno
			$nexttypeno 		= $em	->getRepository('CoreAccountingBundle:Gltrans')
										->findnexttypeno();
			return $this->redirect($this->generateUrl('CoreAccountingBundle_transactions_gltrans_add',
					array('typeno' => $nexttypeno)),301);
		} else {
			$nextcounterindex = $em	->getRepository('CoreAccountingBundle:Gltrans')
									->findnextcounterindex();
			$newentry = new Journal();
			$newentry->setTypeno($typeno);
			$newentry->setTrandate(date('Y-m-d'));
			$newentry->setPeriodno($this->getThePeriod());
			$form = $this->createForm(new JournalsType(), $newentry);
			$request = $this->getRequest();
			if ($request->getMethod() == 'POST') {
				$form->bind($request);
				$formData = $form->getData();
				
				$typeno = $newentry->getTypeno();
				$date = $formData->getTrandate();
				$trandate = new \DateTime($date);
				$periodno = $formData->getPeriodno();
				$tag = $formData->getTag()->getTagref();
				
				if ($form->isValid()) {
					foreach ($formData->getJournalentries() as $entryItem) {
						$journalentry = new Gltrans();
						
						$journalentry->setCounterindex($nextcounterindex);
						$journalentry->setType(0);
						$journalentry->setTypeno($typeno);
						$journalentry->setChequeno(0);
						$journalentry->setTrandate($trandate);
						$journalentry->setPeriodno($periodno);
						$account = $entryItem->getAccount();
						$journalentry->setAccount($account);
						$narrative = $entryItem->getNarrative();
						$journalentry->setNarrative($narrative);
						$amount = $entryItem->getAmount();
						$journalentry->setAmount($amount);
						$journalentry->setPosted(0);
						$journalentry->setJobref('_');
						$journalentry->setTag($tag);
		        		$em->persist($journalentry);
		        								
		        		$nextcounterindex++;
					}
					$em->flush();
					$returnMessage = "Journal entry $typeno successfully updated.";
				} else {
					$returnMessage = "An error occurred during the processing of entry $typeno.";
				}
	        	$session = $this->getRequest()->getSession();
	        	$session->getFlashBag()->add('returnMessage',$returnMessage);
	        	return $this->redirect($this->generateUrl('CoreAccountingBundle_transactions_gltrans_add'),301);
			} else {
				return $this->render('CoreAccountingBundle:Transactions:gltransadd.html.twig', array(
						'form'        		=> $form->createView(),
						'counterindex' 		=> $nextcounterindex
				));
			}
		}
	}
	
	/* Adjust Journal Entries - gltrans
	* 		
	*/
	public function searchManualTransactionAction()
	{
		$data = array();
		$form = $this	->createFormBuilder($data)
						->add('typeno','integer')
						->add('Confirm','submit')
						->getForm();
		$request = $this->getRequest();
		if ($request->getMethod() == 'POST') {
			$form->bind($request);
			$typeno = $form['typeno']->getData();
			return $this->redirect($this->generateUrl('CoreAccountingBundle_transactions_gltrans_edit',
					array('typeno' => $typeno)),301);
		} else {
			return $this->render('CoreAccountingBundle:Transactions:gltranssearch.html.twig', array(
							'form' 		=> $form->createView()
			));
		}
	}
	
	public function editManualTransactionAction($typeno)
	{
		$em = $this	->getDoctrine()
					->getManager();
		$journalentry = $this->getJournalentry($typeno,'typeno');
		$typeno = $journalentry[1]->getTypeno();
		$trandate = $journalentry[1]->getTrandate();
		$periodno = $journalentry[1]->getPeriodno();
		$tag = $journalentry[1]->getTag();
		
		$updateentry = new Journal();
		$updateentry->setTypeno($typeno);
		$updateentry->setTrandate($trandate->format('Y-m-d'));
		$updateentry->setPeriodno($periodno);
		$updateentry->setTag($tag);
		$updateentry->setJournalentries($journalentry);
		
		$form = $this->createForm(new JournalsType(), $updateentry);
		
		$request = $this->getRequest();
		if ($request->getMethod() == 'POST') {
			$form->bind($request);
			$formData = $form->getData();
			
			$typeno 	= $formData->getTypeno();
			$date 		= $formData->getTrandate();
			$trandate 	= new \DateTime($date);
			$periodno 	= $formData->getPeriodno();
			$tag 		= $formData->getTag()->getTagref();
			
			if ($form->isValid()) {
				foreach ($formData->getJournalentries() as $entryItem) {
					$account = $entryItem->getAccount();
					$narrative = $entryItem->getNarrative();
					$amount = $entryItem->getAmount();
					
					if($entryItem->getCounterindex() != NULL) {
						$currentCounterindex = $entryItem->getCounterindex();
						$journalentryupdatearray = $this->getJournalentry($currentCounterindex,'counterindex');
						$journalentryupdate = $journalentryupdatearray[0];
						$journalentryupdate->setCounterindex($currentCounterindex);
					} else {
						$journalentryupdate = new Gltrans();
					}
					
					$journalentryupdate->setType(0);
					$journalentryupdate->setTypeno($typeno);
					$journalentryupdate->setChequeno(0);
					$journalentryupdate->setTrandate($trandate);
					$journalentryupdate->setPeriodno($periodno);
					$journalentryupdate->setAccount($account);
					$journalentryupdate->setNarrative($narrative);
					$journalentryupdate->setAmount($amount);
					$journalentryupdate->setPosted(0);
					$journalentryupdate->setJobref('_');
					$journalentryupdate->setTag($tag);
					
					$em->persist($journalentryupdate);
				}
				$em->flush();
				$returnMessage = "Journal entry $typeno successfully updated.";
			} else {
				$returnMessage = "An error occurred during the processing of entry $typeno.";
        	}
        	$session = $this->getRequest()->getSession();
        	$session->getFlashBag()->add('returnMessage',$returnMessage);
        	return $this->redirect($this->generateUrl('CoreAccountingBundle_transactions_gltrans_search'),301);
		} else {
			return $this->render('CoreAccountingBundle:Transactions:gltransedit.html.twig', array(
							'form' 		=> $form->createView()
			));
		}
	}
	
	public function getJournalentry($id,$type) 
	{
		$em = $this->getDoctrine()->getManager();
		switch($type)
		{
			case 'typeno':
				$result = $em	->getRepository('CoreAccountingBundle:Gltrans')
								->findBytypeno($id);
				break;
			case 'counterindex':
				$result = $em	->getRepository('CoreAccountingBundle:Gltrans')
								->findBycounterindex($id);
				break;
		}
		return $result;
	}
	
	protected function getThePeriod($date=null)
	{
		$em = $this->getDoctrine()->getManager();
		if(isset($date)) {
			$today = new \DateTime($date,new \DateTimeZone('America/Chicago'));
		} else {
			$today = new \DateTime('today',new \DateTimeZone('America/Chicago'));
		}
		$periods = $em	->getRepository('CoreAccountingBundle:Periods')
						->findperiodnowithdate($today);
		if (!$periods) {
			throw $this->createNotFoundException('Unable to find Fiscal Period.');
		}
		return $periods;
	}
	
	protected function getTheLastDate($period)
	{
		$em = $this->getDoctrine()->getManager();
		$lastdate = $em	->getRepository('CoreAccountingBundle:Periods')
						->findlastdatewithperiodno($period);
		if (!$lastdate) {
			throw $this->createNotFoundException('Unable to find Fiscal Period.');
		}
		return $lastdate;
	}
	
	protected function getBatchPeriods($type)
	{
		$em = $this->getDoctrine()->getManager();
		switch ($type)
		{
			case 'start':
				$id = 1;
				break;
				
			case 'end':
				$id = 9;
				break;
		}
		$period = $em	->getRepository('CoreAccountingBundle:Gltrans')
						->findperiodsbytagnotstarted($id);
		if (!$period) {
			throw $this->createNotFoundException('Unable to find Fiscal Period.');
		}
		$start = $period[0]['period']-2;
		$end = $period[0]['period']+3;
		$periods = array();
		for ($start; $start <= $end; $start++) {
			$thedate = $this->getTheLastDate($start);
			$periods[] = array( 'periodnbr' => $start,
								'lastdate'	=> $thedate[0]['lastdateinperiod']->format('M Y'));
		}
		return $periods;
	}
	
	/* Adjust Journal Entries - gltrans
	 *
	*/
	protected function getImporttransdefin($id=null)
	{
		$em = $this->getDoctrine()->getManager();
		if(isset($id)) {
			$definitions = $em	->getRepository('CoreAccountingBundle:Importtransdefn')
								->findByimportdefnid($id);
		} else {
			$definitions = $em	->getRepository('CoreAccountingBundle:Importtransdefn')
								->findAll();
		}
		return $definitions;
	}
	
	protected function getDocuments($id=null)
	{
		$em = $this->getDoctrine()->getManager();
		if(isset($id)) {
			$documents = $em	->getRepository('CoreAccountingBundle:Document')
								->findById($id);
		} else {
			$documents = $em	->getRepository('CoreAccountingBundle:Document')
								->findAll();
		}
		return $documents;
	}
	
	public function showBatchTransactionAction()
	{
		$periodrangestart = $this->getBatchPeriods('start');
		$periodrangeend = $this->getBatchPeriods('end');
		$importoptions = $this->getImporttransdefin();
		$documents = $this->getDocuments();
		return $this->render('CoreAccountingBundle:Transactions:batchmenushow.html.twig', array(
				'periodrangestart' 	=> $periodrangestart,
				'periodrangeend' 	=> $periodrangeend,
				'importoptions'		=> $importoptions,
				'documents'			=> $documents
		));
	}
	
	public function uploadTransactionAction(Request $request,$id)
	{
		$document = new Document();
		$tempimportoption = $this->getImporttransdefin($id);
		$importoption = $tempimportoption[0];
		$form = $this->createForm(new DocumentType(), $document);
		$form->handleRequest($request);
		
		if ($request->getMethod() == 'POST') {
			if ($form->isValid()) {
				$em = $this->getDoctrine()->getManager();
				
				$document->setId($id);
				$document->setName($document->getfile()->getClientOriginalName());
				$em->persist($document);
				$em->flush();
				$returnMessage = 'File successfully loaded.';
			} else {
        		$returnMessage = 'An error occurred during the loading of the file.';
			}
        	$session = $this->getRequest()->getSession();
        	$session->getFlashBag()->add('returnMessage',$returnMessage);
			return $this->redirect($this->generateUrl('CoreAccountingBundle_transactions_batch_menu'),301);
		} else {
			return $this->render('CoreAccountingBundle:Transactions:batchupload.html.twig', array(
					'form'		=> $form->createView(),
					'importname'=> $importoption 
			));
		}
	}
	
	public function processTransactionAction($id)
	{
		$em = $this	->getDoctrine()
					->getManager();
		$documents = $this->getDocuments($id);
		$fileObj = new \SplFileObject($documents[0]->getPath());
		$tempimportoption = $this->getImporttransdefin($id);
		$importoption = $tempimportoption[0];
		
		$dataheader = json_decode($importoption->getDataheaderdefn());
		
		$beginIndicator = 0;
		if($dataheader->{'search'}=="none") {
			$searchCount = 0;
		} else {
			$searchCount = count($dataheader->{'search'});
		}
		
		$nexttypeno 		= $em	->getRepository('CoreAccountingBundle:Gltrans')
									->findnexttypeno();
		$nextcounterindex 	= $em	->getRepository('CoreAccountingBundle:Gltrans')
									->findnextcounterindex();
		
		
		
		while(!$fileObj->eof()) {
			if($line[0]!=NULL) {
				if($beginIndicator==$searchCount) {
					// read file as normal into Gltrans object
					$trandate = $line[$importoption->getDatalineaccount()];
					$periodno = 0;
					$account = 0;
					$narrative = 0;
					$amount = 0;
					$tag = 0;
					
					$singlejournalentry = new Gltrans;
					$singlejournalentry->setCounterindex($nextcounterindex);
					$singlejournalentry->setType(0);
					$singlejournalentry->setTypeno($nexttypeno);
					$singlejournalentry->setChequeno(0);
					$singlejournalentry->setTrandate($trandate);
					$singlejournalentry->setPeriodno($periodno);
					$singlejournalentry->setAccount($account);
					$singlejournalentry->setNarrative($narrative);
					$singlejournalentry->setAmount($amount);
					$singlejournalentry->setPosted(0);
					$singlejournalentry->setJobref('_');
					$singlejournalentry->setTag($tag);
					
					
					
					
					
				} else {
					foreach($line AS $element) {
						if($beginIndicator==$searchCount) break;
						if($dataheader->{'search'}=="none") {
							$beginIndicator = $searchCount;
						} elseif(strpos($element,$dataheader->{'search'}[$beginIndicator]->{$beginIndicator})!==false) {
							$beginIndicator++;
						}
					}
				}
			}
		} // end of while
		
		
		echo 'file</br>';\Doctrine\Common\Util\Debug::dump($file);echo '</br></br>';
		echo 'importoption</br>';\Doctrine\Common\Util\Debug::dump($importoption);echo '</br></br>';
		
		die();
		
		
	}
	
	public function deleteUploadAction($id)
	{
		$em = $this	->getDoctrine()
					->getManager();
		$document = $this->getDocuments($id);
		
		$em->remove($document[0]);
		$em->flush();
		$returnMessage = 'File successfully removed.';
		$session = $this->getRequest()->getSession();
		$session->getFlashBag()->add('returnMessage',$returnMessage);
		return $this->redirect($this->generateUrl('CoreAccountingBundle_transactions_batch_menu'),301);
	}
	
	protected function getStandardMonthJournal($period,$stage)
	{
		$em = $this->getDoctrine()->getManager();
		$accounts 			= $em	->getRepository('CoreAccountingBundle:Chartmaster')
									->findAll();
		$nexttypeno 		= $em	->getRepository('CoreAccountingBundle:Gltrans')
									->findnexttypeno();
		$nextcounterindex 	= $em	->getRepository('CoreAccountingBundle:Gltrans')
									->findnextcounterindex();
		$transactiondate 	= $em	->getRepository('CoreAccountingBundle:Periods')
									->findfirstdatewithperiodno($period);
		$periodno		 	= $em	->getRepository('CoreAccountingBundle:Periods')
									->findOneByperiodno($period);
		
		$newentry = new Journal();
		foreach ($accounts as $key => $account) {
			if(is_numeric(substr($account->getAccountname(),-6))) {
				
				$journalentry = new Gltrans();
				$id = substr($account->getAccountname(),-6);
				$accountchartdetails = $em	->getRepository('CoreAccountingBundle:Chartdetails')
											->findBudgetActualbyaccountandperiod($id,$period);
				
				if($stage == 'start') {
					$amount = $accountchartdetails->getBudget();
					$narrative = "Monthly Accurals";
				} elseif($stage == 'end') {
					$amount = $accountchartdetails->getActual();
					$narrative = "Monthly Close";
				}
				
			
				$journalentry->setCounterindex($nextcounterindex);
				$journalentry->setType(0);
				$journalentry->setTypeno($nexttypeno);
				$journalentry->setChequeno(0);
				$journalentry->setTrandate($transactiondate);
				$journalentry->setPeriodno($periodno);
				$journalentry->setAccount($account);
				$journalentry->setNarrative($narrative);
				$journalentry->setAmount($amount);
				$journalentry->setPosted(0);
				$journalentry->setJobref('_');
				$journalentry->setTag(1);
				$newentry->addJournalentries($journalentry);
				$nextcounterindex++;
			}
		}
		$journals =  $newentry->getJournalentries();
		
		// add balancing transaction from cash here
		// cash balance acct is 110120
		
		$runningTotal = 0;
		foreach($journals as $journal) {
			$runningTotal = $runningTotal + $journal->getAmount();
		}
		$runningTotal = $runningTotal*-1;
		
		$journalentry = new Gltrans();
		$journalentry->setCounterindex($nextcounterindex);
		$journalentry->setType(0);
		$journalentry->setTypeno($nexttypeno);
		$journalentry->setChequeno(0);
		$journalentry->setTrandate($transactiondate);
		$journalentry->setPeriodno($periodno);
		$journalentry->setAccount('110120');
		$journalentry->setNarrative($narrative);
		$journalentry->setAmount($runningTotal);
		$journalentry->setPosted(0);
		$journalentry->setJobref('_');
		$journalentry->setTag(1);
		$newentry->addJournalentries($journalentry);
		
		$newentry->setTypeno($journals[0]->getTypeno());
		$newentry->setTrandate($journals[0]->getTrandate());
		$newentry->setPeriodno($journals[0]->getPeriodno());
		$newentry->setTag($journals[0]->getTag());
		
		return $newentry;
	}
	
	public function editBatchTransactionAction($period,$stage)
	{
		$em = $this->getDoctrine()->getManager();

		$journalentryupdate = $this->getStandardMonthJournal($period,$stage);
		$typeno 	= $journalentryupdate->getTypeno();
		$trandate 	= $journalentryupdate->getTrandate();
		$periodno 	= $journalentryupdate->getPeriodno();
		$tag 		= $journalentryupdate->getTag();
		
		$updateentry = new Journal();
		$updateentry->setTypeno($typeno);
		$updateentry->setTrandate($trandate);
		$updateentry->setPeriodno($periodno);
		$updateentry->setTag($tag);
		$updateentry->setJournalentries($journalentryupdate->getJournalentries());
		
		$form = $this->createForm(new JournalsType(), $updateentry);
		$request = $this->getRequest();
		if ($request->getMethod() == 'POST') {
			$form->bind($request);
			$formData = $form->getData();
			
			$typeno 	= $formData->getTypeno();
			$date 		= $formData->getTrandate();
			$trandate 	= new \DateTime($date);
			$periodno 	= $formData->getPeriodno();
			$tag 		= $formData->getTag()->getTagref();
			
			if ($form->isValid()) {
				foreach ($formData->getJournalentries() as $entryItem) {
					$account = $entryItem->getAccount();
					$narrative = $entryItem->getNarrative();
					$amount = $entryItem->getAmount();
					
					if($entryItem->getCounterindex() != NULL) {
						$journalentryupdate = $entryItem;
					} else {
						$journalentryupdate = new Gltrans();
					}
					$journalentryupdate->setType(0);
					$journalentryupdate->setTypeno($typeno);
					$journalentryupdate->setChequeno(0);
					$journalentryupdate->setTrandate($trandate);
					$journalentryupdate->setPeriodno($periodno);
					$journalentryupdate->setAccount($account);
					$journalentryupdate->setNarrative($narrative);
					$journalentryupdate->setAmount($amount);
					$journalentryupdate->setPosted(0);
					$journalentryupdate->setJobref('_');
					$journalentryupdate->setTag($tag);
					$em->persist($journalentryupdate);
				}
				$em->flush();
				$returnMessage = "Month start journal entries with Type Nbr $typeno successfully updated.";
			} else {
				$returnMessage = "An error occurred during the processing of month start journal entries with Type Nbr $typeno.";
        	}
        	$session = $this->getRequest()->getSession();
        	$session->getFlashBag()->add('returnMessage',$returnMessage);
        	return $this->redirect($this->generateUrl('CoreAccountingBundle_transactions_batch_menu'),301);
		} else {
			return $this->render('CoreAccountingBundle:Transactions:gltransedit.html.twig', array(
							'form' 		=> $form->createView()
			));
		}
	}
}