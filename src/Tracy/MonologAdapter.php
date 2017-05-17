<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Monolog\Tracy;

use Monolog;
use Tracy\BlueScreen;
use Tracy\Debugger;
use Tracy\Helpers;
use Tracy\Logger;



/**
 * Replaces the default Tracy logger,
 * which allows to preprocess all messages and pass then to Monolog for processing.
 *
 * @author Martin Bažík <martin@bazo.sk>
 * @author Filip Procházka <filip@prochazka.su>
 */
class MonologAdapter extends Logger
{

	const ACCESS = 'access';

	/**
	 * @var array
	 */
	private $priorityMap = [
		self::DEBUG => Monolog\Logger::DEBUG,
		self::INFO => Monolog\Logger::INFO,
		self::WARNING => Monolog\Logger::WARNING,
		self::ERROR => Monolog\Logger::ERROR,
		self::EXCEPTION => Monolog\Logger::CRITICAL,
		self::CRITICAL => Monolog\Logger::CRITICAL
	];

	/**
	 * @var Monolog\Logger
	 */
	private $monolog;



	public function __construct(Monolog\Logger $monolog, $logDirectory, $email = NULL, BlueScreen $blueScreen = NULL)
	{
		parent::__construct($logDirectory, $email, $blueScreen);
		$this->monolog = $monolog;
	}



	/**
	 * @param \Exception|\Throwable $exception
	 * @param string $file
	 * @return string logged error filename
	 */
	public function renderToFile($exception, $file)
	{
		return parent::logException($exception, $file);
	}



	public function log($originalMessage, $priority = self::INFO)
	{
		$message = $this->formatMessage($originalMessage);
		$context = [
			'priority' => $priority,
			'at' => Helpers::getSource(),
		];

		if ($originalMessage instanceof \Throwable) {
			$context['exception'] = $originalMessage;
		}

		$exceptionFile = ($originalMessage instanceof \Throwable)
			? $this->getExceptionFile($originalMessage)
			: NULL;

		if ($this->email !== NULL && $this->mailer !== NULL && in_array($priority, [self::ERROR, self::EXCEPTION, self::CRITICAL], TRUE)) {
			$this->sendEmail(implode('', [
				@date('[Y-m-d H-i-s]'),
				$message,
				' @ ' . Helpers::getSource(),
				($exceptionFile !== NULL) ? ' @@ ' . basename($exceptionFile) : NULL
			]));
		}

		switch ($priority) {
			case self::ACCESS:
				$this->monolog->addInfo($message, $context);
				break;

			default:
				$this->monolog->addRecord(
					$this->getLevel($priority),
					$message,
					$context
				);
		}

		return $exceptionFile;
	}


	/**
	 * @param string $priority
	 * @return int
	 */
	protected function getLevel($priority)
	{
		if (isset($this->priorityMap[$priority])) {
			return $this->priorityMap[$priority];
		}

		$levels = Monolog\Logger::getLevels();
		return isset($levels[$uPriority = strtoupper($priority)]) ? $levels[$uPriority] : Monolog\Logger::INFO;
	}

}