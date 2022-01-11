<?php
class SpecialVote extends SpecialPage {
	function __construct() {
		parent::__construct('Vote', 'vote');
	}

	function handleVoteSubmission() {
		global $wgElectionCandidates, $wgElectionId;

		$request = $this->getRequest();
		$output = $this->getOutput();

		if ( !$this->getUser()->matchEditToken( $request->getVal( 'token' ) ) ) {
			$output->addWikiMsg('sessionfailure');
			$output->addReturnTo($this->getPageTitle());
			return;
		}

		$votes = $request->getArray('candidateRank');
		if (!is_array($votes)) {
			// Most likely just clicked Vote without selecting anything
			$output->addWikiMsg('election-error-empty-form');
			$output->addReturnTo($this->getPageTitle());
			return;
		}
		$voteRepo = new ElectionVoteRepository(__METHOD__, $wgElectionId);
		$message = $voteRepo->addVotes($this->getUser(), $votes);

		if ($message) {
			$output->addWikiMsg('election-error-' . $message);
			// Return to voting page for recoverable errors (e.g. illegal voting)
			// but return to main page for others (e.g. already voted)
			if ($message === 'duplicate-missing' || $message === 'illegal-voting') {
				$output->addReturnTo($this->getPageTitle());
			} else {
				$output->returnToMain();
			}
			return;
		}

		$output->addWikiMsg('election-vote-thanks');
		$output->returnToMain();
	}

	function showElectionInactivePage() {
		$output = $this->getOutput();

		$output->addWikiMsg('election-error-inactive');
	}

	function showAlreadyVotedPage() {
		$output = $this->getOutput();

		$output->addWikiMsg('election-error-voted');
	}

	function showBlockedPage() {
		$output = $this->getOutput();

		$output->addWikiMsg('election-error-blocked');
	}

	function showUserTooNewPage() {
		$output = $this->getOutput();

		$output->addWikiMsg('election-error-age');
	}

	function showVoteForm() {
		global $wgElectionCandidates, $wgElectionCountMethod;
		$output = $this->getOutput();

		$numCandidates = count($wgElectionCandidates);

		$output->addWikiMsg('election-vote-description', 1, $numCandidates);

		$output->addHTML(Html::openElement('form', [
			'method' => 'post',
			'enctype' => 'multipart/form-data',
			'action' => $this->getPageTitle()->getFullURL()
		]));

		$output->addHTML(Html::openElement('table', ['class' => 'wikitable']));

		switch ($wgElectionCountMethod) {
			case 'confidence':
				$output->addHTML(Html::openElement('tr'));
				$output->addHTML(Html::element('th', ['scope' => 'row']));
				$output->addHTML(Html::element(
					'th', ['scope' => 'col'],
					$this->msg('election-vote-yes')->text()
				));
				$output->addHTML(Html::element(
					'th', ['scope' => 'col'],
					$this->msg('election-vote-no')->text()
				));
				$output->addHTML(Html::closeElement('tr'));

				foreach ($wgElectionCandidates as $candidateId => $candidateName) {
					$output->addHTML(Html::openElement('tr'));
					$output->addHTML(Html::element('th', ['scope' => 'row'], $candidateName));
					$output->addHTML(Html::rawElement(
						'td', ['style' => 'text-align: center'],
						Html::radio('candidateRank[' . $candidateId . ']', false, [
							'value' => 1
						])
					));
					$output->addHTML(Html::rawElement(
						'td', ['style' => 'text-align: center'],
						Html::radio('candidateRank[' . $candidateId . ']', false, [
							'value' => 0
						])
					));
					$output->addHTML(Html::closeElement('tr'));
				}
				break;
			case 'borda':
			default:
				$output->addHTML(Html::openElement('tr'));
				$output->addHTML(Html::element('th', ['scope' => 'row']));
				for ($rankIdx = 1; $rankIdx <= $numCandidates; $rankIdx++) {
					$colHeader = $rankIdx;
					if ($rankIdx === 1) {
						$colHeader = $this->msg('election-vote-most-preferred')
							->params($rankIdx)->text();
					} elseif ($rankIdx === $numCandidates) {
						$colHeader = $this->msg('election-vote-least-preferred')
							->params($rankIdx)->text();
					}
					$output->addHTML(Html::element('th', ['scope' => 'col'], $colHeader));
				}
				$output->addHTML(Html::closeElement('tr'));

				foreach ($wgElectionCandidates as $candidateId => $candidateName) {
					$output->addHTML(Html::openElement('tr'));
					$output->addHTML(Html::element('th', ['scope' => 'row'], $candidateName));
					for ($rankIdx = 1; $rankIdx <= $numCandidates; $rankIdx++) {
						$output->addHTML(Html::rawElement(
							'td', ['style' => 'text-align: center'],
							Html::radio('candidateRank[' . $candidateId . ']', false, [
								'value' => $rankIdx
							])
						));
					}
					$output->addHTML(Html::closeElement('tr'));
				}
		}
		$output->addHTML(Html::closeElement('table'));

		$output->addHTML(Html::hidden('token', $this->getUser()->getEditToken()));

		$output->addHTML(Html::element('input', [
			'type' => 'submit',
			'value' => $this->msg('election-vote-button')->text()
		]));

		$output->addHTML(Html::closeElement('form'));
	}

	function showVotePage() {
		global $wgElectionActive, $wgElectionId, $wgElectionMinRegistrationDate;

		if (!$wgElectionActive) {
			$this->showElectionInactivePage(); return;
		}

		if ($this->getUser()->getBlock()) {
			$this->showBlockedPage(); return;
		}

		if (wfTimestamp(TS_UNIX, $this->getUser()->getRegistration()) < $wgElectionMinRegistrationDate) {
			$this->showUserTooNewPage(); return;
		}

		$voteLoader = new ElectionVoteLoader(__METHOD__, $wgElectionId);
		if ($voteLoader->hasUserVoted($this->getUser())) {
			$this->showAlreadyVotedPage(); return;
		}

		$this->showVoteForm();
	}

	function execute($par) {
		$request = $this->getRequest();
		$this->getOutput()->setPageTitle($this->msg('election-vote-title')->escaped());
		$this->checkPermissions();
		$this->checkReadOnly();

		if ($request->wasPosted()) {
			$this->handleVoteSubmission();
		} else {
			$this->showVotePage();
		}
	}
}