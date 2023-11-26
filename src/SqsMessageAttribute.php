<?php

namespace ssigwart\AwsHighAvailabilitySqs;

/** SQS message attribute */
class SqsMessageAttribute
{
	/** @var string Data type */
	private string $dataType;

	/** @var string Data type: String */
	const DATA_TYPE_STRING = 'String';
	/** @var string Data type: Number */
	const DATA_TYPE_NUMBER = 'Number';

	/** @var string String value */
	private string $stringValue;

	/**
	 * Constructor
	 *
	 * @param string $dataType Data type
	 * @param string $stringValue String value
	 */
	public function __construct(string $dataType, string $stringValue)
	{
		$this->dataType = $dataType;
		$this->stringValue = $stringValue;
	}

	/**
	 * Get data type
	 *
	 * @return string Data type
	 */
	public function getDataType(): string
	{
		return $this->dataType;
	}

	/**
	 * Get string value
	 *
	 * @return string String value
	 */
	public function getStringValue(): string
	{
		return $this->stringValue;
	}
}
