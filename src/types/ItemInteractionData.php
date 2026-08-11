<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\types\inventory\InventoryTransactionChangedSlotsHack;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use function count;

final class ItemInteractionData{
	/**
	 * @param InventoryTransactionChangedSlotsHack[] $requestChangedSlots
	 */
	public function __construct(
		private int $requestId,
		private array $requestChangedSlots,
		private UseItemTransactionData $transactionData
	){}

	public function getRequestId() : int{
		return $this->requestId;
	}

	/**
	 * @return InventoryTransactionChangedSlotsHack[]
	 */
	public function getRequestChangedSlots() : array{
		return $this->requestChangedSlots;
	}

	public function getTransactionData() : UseItemTransactionData{
		return $this->transactionData;
	}

	public static function read(ByteBufferReader $in) : self{
		$requestId = VarInt::readSignedInt($in);
		$requestChangedSlots = [];
		if($requestId !== 0){
			$len = VarInt::readUnsignedInt($in);
			for($i = 0; $i < $len; ++$i){
				$requestChangedSlots[] = InventoryTransactionChangedSlotsHack::read($in);
			}
		}
		$transactionData = new UseItemTransactionData();
		$transactionData->decodeAuthInput($in);
		return new ItemInteractionData($requestId, $requestChangedSlots, $transactionData);
	}

	public function write(ByteBufferWriter $out) : void{
		VarInt::writeSignedInt($out, $this->requestId);
		if($this->requestId !== 0){
			VarInt::writeUnsignedInt($out, count($this->requestChangedSlots));
			foreach($this->requestChangedSlots as $changedSlot){
				$changedSlot->write($out);
			}
		}
		$this->transactionData->encodeAuthInput($out);
	}

	/** Protocol 2169+ Cereal PlayerAuthInput embedded interaction layout. Kept under the historical method name for ABI compatibility. */
	public static function read12640(ByteBufferReader $in) : self{
		$requestId = VarInt::readSignedInt($in);
		$requestChangedSlots = [];
		if($requestId !== 0){
			$len = VarInt::readUnsignedInt($in);
			for($i = 0; $i < $len; ++$i){
				$requestChangedSlots[] = InventoryTransactionChangedSlotsHack::read($in);
			}
		}
		$transactionData = new UseItemTransactionData();
		$transactionData->decodeAuthInput12640($in);
		return new self($requestId, $requestChangedSlots, $transactionData);
	}

	public function write12640(ByteBufferWriter $out) : void{
		VarInt::writeSignedInt($out, $this->requestId);
		if($this->requestId !== 0){
			VarInt::writeUnsignedInt($out, count($this->requestChangedSlots));
			foreach($this->requestChangedSlots as $changedSlot){
				$changedSlot->write($out);
			}
		}
		$this->transactionData->encodeAuthInput12640($out);
	}

	/**
	 * Exact PlayerAuthInput embedded item-interaction layout used by the
	 * MP-stable BedrockProtocol 1.26.40 lock.
	 */
	public static function readStable12640(ByteBufferReader $in) : self{
		$requestId = VarInt::readSignedInt($in);
		$requestChangedSlots = [];
		$hasChangedSlots = CommonTypes::getBool($in);
		if($hasChangedSlots && $requestId < -1 && ($requestId & 1) === 0){
			$len = VarInt::readUnsignedInt($in);
			for($i = 0; $i < $len; ++$i){
				$requestChangedSlots[] = InventoryTransactionChangedSlotsHack::read($in);
			}
		}
		$transactionData = new UseItemTransactionData();
		$transactionData->decodeStable12640($in);
		return new self($requestId, $requestChangedSlots, $transactionData);
	}

	public function writeStable12640(ByteBufferWriter $out) : void{
		VarInt::writeSignedInt($out, $this->requestId);
		$hasChangedSlots = $this->requestId < -1 && ($this->requestId & 1) === 0;
		CommonTypes::putBool($out, $hasChangedSlots);
		if($hasChangedSlots){
			VarInt::writeUnsignedInt($out, count($this->requestChangedSlots));
			foreach($this->requestChangedSlots as $changedSlot){
				$changedSlot->write($out);
			}
		}
		$this->transactionData->encodeStable12640($out);
	}
}
