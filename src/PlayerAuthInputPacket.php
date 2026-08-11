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

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\serializer\BitSet;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\network\mcpe\protocol\types\InteractionMode;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\ItemInteractionData;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputVehicleInfo;
use pocketmine\network\mcpe\protocol\types\PlayerBlockAction;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionStopBreak;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionWithBlockInfo;
use pocketmine\network\mcpe\protocol\types\PlayMode;
use function assert;
use function count;

class PlayerAuthInputPacket extends DataPacket implements ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::PLAYER_AUTH_INPUT_PACKET;

	public Vector3 $position;
	private float $pitch;
	private float $yaw;
	private float $headYaw;
	private float $moveVecX;
	private float $moveVecZ;
	private BitSet $inputFlags;
	private int $inputMode;
	private int $playMode;
	private int $interactionMode;
	private ?Vector3 $vrGazeDirection = null;
	private Vector2 $interactRotation;
	private int $tick;
	private Vector3 $delta;
	private ?ItemInteractionData $itemInteractionData = null;
	private ?ItemStackRequest $itemStackRequest = null;
	/** @var PlayerBlockAction[]|null */
	private ?array $blockActions = null;
	private ?PlayerAuthInputVehicleInfo $vehicleInfo = null;
	private ?Vector2 $vehicleRotation = null;
	private ?int $predictedVehicleActorUniqueId = null;
	private float $analogMoveVecX;
	private float $analogMoveVecZ;
	private Vector3 $cameraOrientation;
	private Vector2 $rawMove;

	/**
	 * @generate-create-func
	 * @param PlayerBlockAction[] $blockActions
	 */
	private static function internalCreate(
		Vector3 $position,
		float $pitch,
		float $yaw,
		float $headYaw,
		float $moveVecX,
		float $moveVecZ,
		BitSet $inputFlags,
		int $inputMode,
		int $playMode,
		int $interactionMode,
		?Vector3 $vrGazeDirection,
		Vector2 $interactRotation,
		int $tick,
		Vector3 $delta,
		?ItemInteractionData $itemInteractionData,
		?ItemStackRequest $itemStackRequest,
		?array $blockActions,
		?PlayerAuthInputVehicleInfo $vehicleInfo,
		float $analogMoveVecX,
		float $analogMoveVecZ,
		Vector3 $cameraOrientation,
		Vector2 $rawMove,
	) : self{
		$result = new self;
		$result->position = $position;
		$result->pitch = $pitch;
		$result->yaw = $yaw;
		$result->headYaw = $headYaw;
		$result->moveVecX = $moveVecX;
		$result->moveVecZ = $moveVecZ;
		$result->inputFlags = $inputFlags;
		$result->inputMode = $inputMode;
		$result->playMode = $playMode;
		$result->interactionMode = $interactionMode;
		$result->vrGazeDirection = $vrGazeDirection;
		$result->interactRotation = $interactRotation;
		$result->tick = $tick;
		$result->delta = $delta;
		$result->itemInteractionData = $itemInteractionData;
		$result->itemStackRequest = $itemStackRequest;
		$result->blockActions = $blockActions;
		$result->vehicleInfo = $vehicleInfo;
		$result->analogMoveVecX = $analogMoveVecX;
		$result->analogMoveVecZ = $analogMoveVecZ;
		$result->cameraOrientation = $cameraOrientation;
		$result->rawMove = $rawMove;
		return $result;
	}

	/**
	 * @param BitSet                   $inputFlags @see PlayerAuthInputFlags
	 * @param int                      $inputMode @see InputMode
	 * @param int                      $playMode @see PlayMode
	 * @param int                      $interactionMode @see InteractionMode
	 * @param PlayerBlockAction[]|null $blockActions Blocks that the client has interacted with
	 */
	public static function create(
		Vector3 $position,
		float $pitch,
		float $yaw,
		float $headYaw,
		float $moveVecX,
		float $moveVecZ,
		BitSet $inputFlags,
		int $inputMode,
		int $playMode,
		int $interactionMode,
		?Vector3 $vrGazeDirection,
		Vector2 $interactRotation,
		int $tick,
		Vector3 $delta,
		?ItemInteractionData $itemInteractionData,
		?ItemStackRequest $itemStackRequest,
		?array $blockActions,
		?PlayerAuthInputVehicleInfo $vehicleInfo,
		float $analogMoveVecX,
		float $analogMoveVecZ,
		Vector3 $cameraOrientation,
		Vector2 $rawMove
	) : self{
		if($inputFlags->getLength() !== PlayerAuthInputFlags::NUMBER_OF_FLAGS){
			throw new \InvalidArgumentException("Input flags must be " . PlayerAuthInputFlags::NUMBER_OF_FLAGS . " bits long");
		}

		if($playMode === PlayMode::VR and $vrGazeDirection === null){
			//yuck, can we get a properly written packet just once? ...
			throw new \InvalidArgumentException("Gaze direction must be provided for VR play mode");
		}

		$inputFlags->set(PlayerAuthInputFlags::PERFORM_ITEM_STACK_REQUEST, $itemStackRequest !== null);
		$inputFlags->set(PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION, $itemInteractionData !== null);
		$inputFlags->set(PlayerAuthInputFlags::PERFORM_BLOCK_ACTIONS, $blockActions !== null);
		$inputFlags->set(PlayerAuthInputFlags::IN_CLIENT_PREDICTED_VEHICLE, $vehicleInfo !== null);

		return self::internalCreate(
			$position,
			$pitch,
			$yaw,
			$headYaw,
			$moveVecX,
			$moveVecZ,
			$inputFlags,
			$inputMode,
			$playMode,
			$interactionMode,
			$vrGazeDirection?->asVector3(),
			$interactRotation,
			$tick,
			$delta,
			$itemInteractionData,
			$itemStackRequest,
			$blockActions,
			$vehicleInfo,
			$analogMoveVecX,
			$analogMoveVecZ,
			$cameraOrientation,
			$rawMove
		);
	}

	public function getPosition() : Vector3{
		return $this->position;
	}

	public function getPitch() : float{
		return $this->pitch;
	}

	public function getYaw() : float{
		return $this->yaw;
	}

	public function getHeadYaw() : float{
		return $this->headYaw;
	}

	public function getMoveVecX() : float{
		return $this->moveVecX;
	}

	public function getMoveVecZ() : float{
		return $this->moveVecZ;
	}

	/**
	 * @see PlayerAuthInputFlags
	 */
	public function getInputFlags() : BitSet{
		return $this->inputFlags;
	}

	/**
	 * @see InputMode
	 */
	public function getInputMode() : int{
		return $this->inputMode;
	}

	/**
	 * @see PlayMode
	 */
	public function getPlayMode() : int{
		return $this->playMode;
	}

	/**
	 * @see InteractionMode
	 */
	public function getInteractionMode() : int{
		return $this->interactionMode;
	}

	public function getVrGazeDirection() : ?Vector3{
		return $this->vrGazeDirection;
	}

	public function getInteractRotation() : Vector2{ return $this->interactRotation; }

	public function getTick() : int{
		return $this->tick;
	}

	public function getDelta() : Vector3{
		return $this->delta;
	}

	public function getItemInteractionData() : ?ItemInteractionData{
		return $this->itemInteractionData;
	}

	public function getItemStackRequest() : ?ItemStackRequest{
		return $this->itemStackRequest;
	}

	/**
	 * @return PlayerBlockAction[]|null
	 */
	public function getBlockActions() : ?array{
		return $this->blockActions;
	}

	public function getVehicleInfo() : ?PlayerAuthInputVehicleInfo{ return $this->vehicleInfo; }

	public function getVehicleRotation() : ?Vector2{ return $this->vehicleRotation; }

	public function getPredictedVehicleActorUniqueId() : ?int{ return $this->predictedVehicleActorUniqueId; }

	public function getAnalogMoveVecX() : float{ return $this->analogMoveVecX; }

	public function getAnalogMoveVecZ() : float{ return $this->analogMoveVecZ; }

	public function getCameraOrientation() : Vector3{ return $this->cameraOrientation; }

	public function getRawMove() : Vector2{ return $this->rawMove; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		if($protocolId === ProtocolInfo::PROTOCOL_1_26_40){
			// BuildFix52: exact BedrockProtocol 1.26.40 wire layout from the MP-stable lock
			// (ZyroNetworkMC/BedrockProtocol a8cada917a6bd970cd10fa3ca3471dd4a3b2234e).
			$this->pitch = LE::readFloat($in);
			$this->yaw = LE::readFloat($in);
			$this->position = CommonTypes::getVector3($in);
			$this->moveVecX = LE::readFloat($in);
			$this->moveVecZ = LE::readFloat($in);
			$this->headYaw = LE::readFloat($in);

			// 1.26.40 transmits the active flags as a sparse signed-VarInt list.
			$this->inputFlags = new BitSet(66);
			if(CommonTypes::getBool($in)){
				$count = VarInt::readUnsignedInt($in);
				if($count > 66){
					throw new PacketDecodeException("Too many 1.26.40 input flags ($count)");
				}
				for($i = 0; $i < $count; ++$i){
					$flag = VarInt::readSignedInt($in);
					if($flag < 0 || $flag >= 66){
						throw new PacketDecodeException("Unknown 1.26.40 input flag $flag");
					}
					$this->inputFlags->set($flag, true);
				}
			}

			$this->inputMode = VarInt::readUnsignedInt($in);
			$this->playMode = VarInt::readUnsignedInt($in);
			$this->interactionMode = VarInt::readSignedInt($in);
			$this->interactRotation = CommonTypes::getVector2($in);
			$this->tick = VarInt::readUnsignedLong($in);
			$this->delta = CommonTypes::getVector3($in);

			// MP-stable 1.26.40 has an outer feature-presence bool, followed by the
			// optional value's own presence bool. Do not collapse these two layers.
			if(CommonTypes::getBool($in)){
				$this->itemInteractionData = CommonTypes::readOptional($in, ItemInteractionData::readStable12640(...));
			}
			if(CommonTypes::getBool($in)){
				$this->itemStackRequest = CommonTypes::readOptional($in, fn(ByteBufferReader $in) => ItemStackRequest::read($in, $protocolId));
			}
			if(CommonTypes::getBool($in)){
				$this->blockActions = CommonTypes::readOptional($in, function(ByteBufferReader $in) : array{
					$actions = [];
					$max = VarInt::readUnsignedInt($in);
					if($max > 100){
						throw new PacketDecodeException("Too many block actions in 1.26.40 PlayerAuthInputPacket ($max)");
					}
					for($i = 0; $i < $max; ++$i){
						$actionType = VarInt::readSignedInt($in);
						// In the MP-stable 1.26.40 schema every block action, including
						// STOP_BREAK, carries BlockPos + face.
						$actions[] = PlayerBlockActionWithBlockInfo::read($in, $actionType);
					}
					return $actions;
				});
			}
			if(CommonTypes::getBool($in)){
				$this->vehicleRotation = CommonTypes::readOptional($in, CommonTypes::getVector2(...));
			}
			if(CommonTypes::getBool($in)){
				$this->predictedVehicleActorUniqueId = CommonTypes::readOptional($in, CommonTypes::getActorUniqueId(...));
			}
			$this->analogMoveVecX = LE::readFloat($in);
			$this->analogMoveVecZ = LE::readFloat($in);
			$this->cameraOrientation = CommonTypes::getVector3($in);
			$this->rawMove = CommonTypes::getVector2($in);
			return;
		}
		if($protocolId >= 2169){
			// Protocol 2169 (r/26_u4) migrated PlayerAuthInputPacket to Cereal.
			// 1.26.40 is protocol 2168 and MUST continue using the legacy bitset/flag-gated layout below.
			$this->pitch = LE::readFloat($in);
			$this->yaw = LE::readFloat($in);
			$this->position = CommonTypes::getVector3($in);
			$this->moveVecX = LE::readFloat($in);
			$this->moveVecZ = LE::readFloat($in);
			$this->headYaw = LE::readFloat($in);
			$this->inputFlags = new BitSet(PlayerAuthInputFlags::NUMBER_OF_FLAGS);
			if(CommonTypes::getBool($in)){
				$count = VarInt::readUnsignedInt($in);
				for($i = 0; $i < $count; ++$i){
					$flag = VarInt::readSignedInt($in);
					if($flag < 0 || $flag >= PlayerAuthInputFlags::NUMBER_OF_FLAGS){ throw new PacketDecodeException("Unknown input flag $flag"); }
					$this->inputFlags->set($flag, true);
				}
			}
			$this->inputMode = VarInt::readUnsignedInt($in);
			$this->playMode = VarInt::readUnsignedInt($in);
			$this->interactionMode = VarInt::readSignedInt($in);
			$this->interactRotation = CommonTypes::getVector2($in);
			$this->tick = VarInt::readUnsignedLong($in);
			$this->delta = CommonTypes::getVector3($in);
			// Protocol 2169+ Cereal-style optionals have exactly ONE presence byte.
			// BuildFix23 incorrectly consumed an extra boolean before every readOptional(),
			// shifting the stream as soon as block-breaking/item-interaction data was present.
			$this->itemInteractionData = CommonTypes::readOptional($in, ItemInteractionData::read12640(...));
			$this->itemStackRequest = CommonTypes::readOptional($in, fn(ByteBufferReader $in) => ItemStackRequest::read($in, $protocolId));
			$this->blockActions = CommonTypes::readOptional($in, function(ByteBufferReader $in) : array{
				$actions = [];
				$max = VarInt::readUnsignedInt($in);
				if($max > 100){
					throw new PacketDecodeException("Too many block actions in PlayerAuthInputPacket ($max)");
				}
				for($i = 0; $i < $max; ++$i){
					$actionType = VarInt::readSignedInt($in);
					$actions[] = match(true){
						PlayerBlockActionWithBlockInfo::isValidActionType($actionType) => PlayerBlockActionWithBlockInfo::read($in, $actionType),
						$actionType === PlayerAction::STOP_BREAK => PlayerBlockActionStopBreak::read12640($in),
						default => throw new PacketDecodeException("Unexpected block action type $actionType"),
					};
				}
				return $actions;
			});
			$this->vehicleRotation = CommonTypes::readOptional($in, CommonTypes::getVector2(...));
			$this->predictedVehicleActorUniqueId = CommonTypes::readOptional($in, CommonTypes::getActorUniqueId(...));
			$this->analogMoveVecX = LE::readFloat($in); $this->analogMoveVecZ = LE::readFloat($in);
			$this->cameraOrientation = CommonTypes::getVector3($in); $this->rawMove = CommonTypes::getVector2($in);
			return;
		}
		$this->pitch = LE::readFloat($in); $this->yaw = LE::readFloat($in); $this->position = CommonTypes::getVector3($in);
		$this->moveVecX = LE::readFloat($in); $this->moveVecZ = LE::readFloat($in); $this->headYaw = LE::readFloat($in);
		$this->inputFlags = BitSet::read($in, $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? PlayerAuthInputFlags::NUMBER_OF_FLAGS : ($protocolId >= ProtocolInfo::PROTOCOL_1_21_50 ? PlayerAuthInputFlags::NUMBER_OF_FLAGS_LEGACY : 64));
		$this->inputMode = VarInt::readUnsignedInt($in); $this->playMode = VarInt::readUnsignedInt($in); $this->interactionMode = VarInt::readUnsignedInt($in);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_40){ $this->interactRotation = CommonTypes::getVector2($in); }
		elseif($this->playMode === PlayMode::VR){ $this->vrGazeDirection = CommonTypes::getVector3($in); }
		$this->tick = VarInt::readUnsignedLong($in); $this->delta = CommonTypes::getVector3($in);
		if($this->inputFlags->get(PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION)){ $this->itemInteractionData = ItemInteractionData::read($in); }
		if($this->inputFlags->get(PlayerAuthInputFlags::PERFORM_ITEM_STACK_REQUEST)){ $this->itemStackRequest = ItemStackRequest::read($in, $protocolId); }
		if($this->inputFlags->get(PlayerAuthInputFlags::PERFORM_BLOCK_ACTIONS)){
			$this->blockActions=[]; $max=VarInt::readSignedInt($in);
			for($i=0;$i<$max;++$i){ $actionType=VarInt::readSignedInt($in); $this->blockActions[] = match(true){ PlayerBlockActionWithBlockInfo::isValidActionType($actionType)=>PlayerBlockActionWithBlockInfo::read($in,$actionType), $actionType===PlayerAction::STOP_BREAK=>new PlayerBlockActionStopBreak(), default=>throw new PacketDecodeException("Unexpected block action type $actionType")}; }
		}
		if($this->inputFlags->get(PlayerAuthInputFlags::IN_CLIENT_PREDICTED_VEHICLE) && $protocolId >= ProtocolInfo::PROTOCOL_1_20_60){ $this->vehicleInfo=PlayerAuthInputVehicleInfo::read($in,$protocolId); }
		$this->analogMoveVecX=LE::readFloat($in); $this->analogMoveVecZ=LE::readFloat($in);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_40){ $this->cameraOrientation=CommonTypes::getVector3($in); if($protocolId >= ProtocolInfo::PROTOCOL_1_21_50){ $this->rawMove=CommonTypes::getVector2($in); } }
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		if($protocolId === ProtocolInfo::PROTOCOL_1_26_40){
			LE::writeFloat($out, $this->pitch);
			LE::writeFloat($out, $this->yaw);
			CommonTypes::putVector3($out, $this->position);
			LE::writeFloat($out, $this->moveVecX);
			LE::writeFloat($out, $this->moveVecZ);
			LE::writeFloat($out, $this->headYaw);

			CommonTypes::putBool($out, true);
			$flags = [];
			for($i = 0; $i < 66; ++$i){
				if($this->inputFlags->get($i)){
					$flags[] = $i;
				}
			}
			VarInt::writeUnsignedInt($out, count($flags));
			foreach($flags as $flag){
				VarInt::writeSignedInt($out, $flag);
			}
			VarInt::writeUnsignedInt($out, $this->inputMode);
			VarInt::writeUnsignedInt($out, $this->playMode);
			VarInt::writeSignedInt($out, $this->interactionMode);
			CommonTypes::putVector2($out, $this->interactRotation);
			VarInt::writeUnsignedLong($out, $this->tick);
			CommonTypes::putVector3($out, $this->delta);

			CommonTypes::putBool($out, true);
			CommonTypes::writeOptional($out, $this->itemInteractionData, fn(ByteBufferWriter $out, ItemInteractionData $v) => $v->writeStable12640($out));
			CommonTypes::putBool($out, true);
			CommonTypes::writeOptional($out, $this->itemStackRequest, fn(ByteBufferWriter $out, ItemStackRequest $v) => $v->write($out, $protocolId));
			CommonTypes::putBool($out, true);
			CommonTypes::writeOptional($out, $this->blockActions, function(ByteBufferWriter $out, array $actions) : void{
				VarInt::writeUnsignedInt($out, count($actions));
				foreach($actions as $action){
					VarInt::writeSignedInt($out, $action->getActionType());
					if($action instanceof PlayerBlockActionWithBlockInfo){
						$action->write($out);
					}else{
						// Server never sends this serverbound packet in normal operation.
						// Keep stream shape valid if a synthetic STOP_BREAK is encoded.
						CommonTypes::putBlockPosition($out, new \pocketmine\network\mcpe\protocol\types\BlockPosition(0, 0, 0));
						VarInt::writeSignedInt($out, 0);
					}
				}
			});
			CommonTypes::putBool($out, true);
			CommonTypes::writeOptional($out, $this->vehicleRotation, CommonTypes::putVector2(...));
			CommonTypes::putBool($out, true);
			CommonTypes::writeOptional($out, $this->predictedVehicleActorUniqueId, CommonTypes::putActorUniqueId(...));
			LE::writeFloat($out, $this->analogMoveVecX);
			LE::writeFloat($out, $this->analogMoveVecZ);
			CommonTypes::putVector3($out, $this->cameraOrientation);
			CommonTypes::putVector2($out, $this->rawMove);
			return;
		}
		if($protocolId >= 2169){
			// See decodePayload(): protocol 2168 (1.26.40) is still legacy PlayerAuthInput.
			LE::writeFloat($out,$this->pitch); LE::writeFloat($out,$this->yaw); CommonTypes::putVector3($out,$this->position);
			LE::writeFloat($out,$this->moveVecX); LE::writeFloat($out,$this->moveVecZ); LE::writeFloat($out,$this->headYaw);
			CommonTypes::putBool($out,true); $flags=[];
			for($i=0;$i<PlayerAuthInputFlags::NUMBER_OF_FLAGS;++$i){ if($this->inputFlags->get($i)){ $flags[]=$i; } }
			VarInt::writeUnsignedInt($out,count($flags)); foreach($flags as $flag){ VarInt::writeSignedInt($out,$flag); }
			VarInt::writeUnsignedInt($out,$this->inputMode); VarInt::writeUnsignedInt($out,$this->playMode); VarInt::writeSignedInt($out,$this->interactionMode);
			CommonTypes::putVector2($out,$this->interactRotation); VarInt::writeUnsignedLong($out,$this->tick); CommonTypes::putVector3($out,$this->delta);
			CommonTypes::writeOptional($out, $this->itemInteractionData, fn(ByteBufferWriter $out, ItemInteractionData $v) => $v->write12640($out));
			CommonTypes::writeOptional($out, $this->itemStackRequest, fn(ByteBufferWriter $out, ItemStackRequest $v) => $v->write($out, $protocolId));
			CommonTypes::writeOptional($out, $this->blockActions, function(ByteBufferWriter $out, array $actions) : void{
				VarInt::writeUnsignedInt($out, count($actions));
				foreach($actions as $a){
					VarInt::writeSignedInt($out, $a->getActionType());
					if($a instanceof PlayerBlockActionStopBreak){
						$a->write12640($out);
					}else{
						$a->write($out);
					}
				}
			});
			$vehicleRotation=$this->vehicleRotation; $predictedId=$this->predictedVehicleActorUniqueId;
			if($this->vehicleInfo !== null){ $vehicleRotation=new Vector2($this->vehicleInfo->getVehicleRotationX() ?? 0.0,$this->vehicleInfo->getVehicleRotationZ() ?? 0.0); $predictedId=$this->vehicleInfo->getPredictedVehicleActorUniqueId(); }
			CommonTypes::writeOptional($out, $vehicleRotation, CommonTypes::putVector2(...));
			CommonTypes::writeOptional($out, $predictedId, CommonTypes::putActorUniqueId(...));
			LE::writeFloat($out,$this->analogMoveVecX); LE::writeFloat($out,$this->analogMoveVecZ); CommonTypes::putVector3($out,$this->cameraOrientation); CommonTypes::putVector2($out,$this->rawMove);
			return;
		}
		$inputFlags=$this->inputFlags; if($this->vehicleInfo!==null && $protocolId>=ProtocolInfo::PROTOCOL_1_20_60){$inputFlags->set(PlayerAuthInputFlags::IN_CLIENT_PREDICTED_VEHICLE,true);}
		LE::writeFloat($out,$this->pitch); LE::writeFloat($out,$this->yaw); CommonTypes::putVector3($out,$this->position); LE::writeFloat($out,$this->moveVecX); LE::writeFloat($out,$this->moveVecZ); LE::writeFloat($out,$this->headYaw);
		$this->inputFlags->write($out,$protocolId>=ProtocolInfo::PROTOCOL_1_26_40?PlayerAuthInputFlags::NUMBER_OF_FLAGS:($protocolId>=ProtocolInfo::PROTOCOL_1_21_50?PlayerAuthInputFlags::NUMBER_OF_FLAGS_LEGACY:64));
		VarInt::writeUnsignedInt($out,$this->inputMode); VarInt::writeUnsignedInt($out,$this->playMode); VarInt::writeUnsignedInt($out,$this->interactionMode);
		if($protocolId>=ProtocolInfo::PROTOCOL_1_21_40){CommonTypes::putVector2($out,$this->interactRotation);}elseif($this->playMode===PlayMode::VR){assert($this->vrGazeDirection!==null);CommonTypes::putVector3($out,$this->vrGazeDirection);}
		VarInt::writeUnsignedLong($out,$this->tick); CommonTypes::putVector3($out,$this->delta);
		if($this->itemInteractionData!==null){$this->itemInteractionData->write($out);} if($this->itemStackRequest!==null){$this->itemStackRequest->write($out,$protocolId);} if($this->blockActions!==null){VarInt::writeSignedInt($out,count($this->blockActions));foreach($this->blockActions as $a){VarInt::writeSignedInt($out,$a->getActionType());$a->write($out);}}
		if($this->vehicleInfo!==null && $protocolId>=ProtocolInfo::PROTOCOL_1_20_60){$this->vehicleInfo->write($out,$protocolId);} LE::writeFloat($out,$this->analogMoveVecX);LE::writeFloat($out,$this->analogMoveVecZ);
		if($protocolId>=ProtocolInfo::PROTOCOL_1_21_40){CommonTypes::putVector3($out,$this->cameraOrientation);if($protocolId>=ProtocolInfo::PROTOCOL_1_21_50){CommonTypes::putVector2($out,$this->rawMove);}}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handlePlayerAuthInput($this);
	}
}
