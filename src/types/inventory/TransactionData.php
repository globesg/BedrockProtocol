<?php

/* Native multi-protocol transaction codec: legacy NG + Bedrock 1.26.40 */
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types\inventory;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use function count;

abstract class TransactionData{
    /** @var NetworkInventoryAction[] */
    protected array $actions = [];
    private bool $dataPresent = false;

    /** @return NetworkInventoryAction[] */
    final public function getActions() : array{ return $this->actions; }
    final public function hasData() : bool{ return $this->dataPresent; }
    abstract public function getTypeId() : int;

    /** @throws DataDecodeException @throws PacketDecodeException */
    final public function decodeTransaction(ByteBufferReader $in, int $protocolId) : void{
        if($protocolId === ProtocolInfo::PROTOCOL_1_26_40){
            $this->decodeStable12640($in);
            return;
        }
        if($protocolId > ProtocolInfo::PROTOCOL_1_26_40){
            $this->decode12640($in);
            return;
        }
        $this->dataPresent = true;
        $actionCount = VarInt::readUnsignedInt($in);
        $this->actions = [];
        for($i = 0; $i < $actionCount; ++$i){
            $this->actions[] = (new NetworkInventoryAction())->readTransaction($in, $protocolId);
        }
        $this->decodeData($in, $protocolId);
    }

    /** 1.26.40 tagged optional transaction body. */
    final public function decode12640(ByteBufferReader $in) : void{
        $this->actions = [];
        $this->dataPresent = CommonTypes::getBool($in);
        if($this->dataPresent){
            $actionCount = VarInt::readUnsignedInt($in);
            for($i = 0; $i < $actionCount; ++$i){
                $this->actions[] = (new NetworkInventoryAction())->read12640($in);
            }
            $this->decodeData12640($in);
        }
    }

    /** Legacy PlayerAuthInput embedded transaction. */
    final public function decodeAuthInput(ByteBufferReader $in) : void{
        $this->dataPresent = true;
        $actionCount = VarInt::readUnsignedInt($in);
        $this->actions = [];
        for($i = 0; $i < $actionCount; ++$i){
            $this->actions[] = (new NetworkInventoryAction())->readAuthInput($in);
        }
    }

    /**
     * Protocol 2169+ Cereal PlayerAuthInput embeds a REQUIRED ItemUseInventoryTransaction.
     * Unlike the standalone 1.26.40 transaction codec, there is no extra dataPresent
     * boolean around the transaction body here.
     */
    final public function decodeAuthInput12640(ByteBufferReader $in) : void{
        $this->dataPresent = true;
        $actionCount = VarInt::readUnsignedInt($in);
        if($actionCount > 100){
            throw new PacketDecodeException("Too many inventory actions in PlayerAuthInput transaction ($actionCount)");
        }
        $this->actions = [];
        for($i = 0; $i < $actionCount; ++$i){
            $this->actions[] = (new NetworkInventoryAction())->read12640($in);
        }
        $this->decodeData12640($in);
    }


    /**
     * Exact transaction-body envelope used by the MP-stable BedrockProtocol
     * 1.26.40 lock when embedded in PlayerAuthInput ItemInteractionData.
     */
    final public function decodeStable12640(ByteBufferReader $in) : void{
        $this->actions = [];
        $this->dataPresent = CommonTypes::getBool($in);
        if(!$this->dataPresent){
            return;
        }
        $actionCount = VarInt::readUnsignedInt($in);
        if($actionCount > 100){
            throw new PacketDecodeException("Too many inventory actions in 1.26.40 transaction ($actionCount)");
        }
        for($i = 0; $i < $actionCount; ++$i){
            $this->actions[] = (new NetworkInventoryAction())->read12640($in);
        }
        $this->decodeDataStable12640($in);
    }

    protected function decodeDataStable12640(ByteBufferReader $in) : void{
        $this->decodeData12640($in);
    }

    final public function encodeStable12640(ByteBufferWriter $out) : void{
        CommonTypes::putBool($out, $hasValue = count($this->actions) > 0);
        if(!$hasValue){
            return;
        }
        VarInt::writeUnsignedInt($out, count($this->actions));
        foreach($this->actions as $action){
            $action->write12640($out);
        }
        $this->encodeDataStable12640($out);
    }

    protected function encodeDataStable12640(ByteBufferWriter $out) : void{
        $this->encodeData12640($out);
    }

    abstract protected function decodeData(ByteBufferReader $in, int $protocolId) : void;
    protected function decodeData12640(ByteBufferReader $in) : void{
        $this->decodeData($in, ProtocolInfo::PROTOCOL_1_26_40);
    }

    final public function encodeTransaction(ByteBufferWriter $out, int $protocolId) : void{
        if($protocolId === ProtocolInfo::PROTOCOL_1_26_40){
            $this->encodeStable12640($out);
            return;
        }
        if($protocolId > ProtocolInfo::PROTOCOL_1_26_40){
            $this->encode12640($out);
            return;
        }
        VarInt::writeUnsignedInt($out, count($this->actions));
        foreach($this->actions as $action){ $action->writeTransaction($out, $protocolId); }
        $this->encodeData($out, $protocolId);
    }

    final public function encode12640(ByteBufferWriter $out) : void{
        CommonTypes::putBool($out, $hasValue = count($this->actions) > 0);
        if($hasValue){
            VarInt::writeUnsignedInt($out, count($this->actions));
            foreach($this->actions as $action){ $action->write12640($out); }
            $this->encodeData12640($out);
        }
    }

    final public function encodeAuthInput(ByteBufferWriter $out) : void{
        VarInt::writeUnsignedInt($out, count($this->actions));
        foreach($this->actions as $action){ $action->writeAuthInput($out); }
    }

    final public function encodeAuthInput12640(ByteBufferWriter $out) : void{
        VarInt::writeUnsignedInt($out, count($this->actions));
        foreach($this->actions as $action){
            $action->write12640($out);
        }
        $this->encodeData12640($out);
    }

    abstract protected function encodeData(ByteBufferWriter $out, int $protocolId) : void;
    protected function encodeData12640(ByteBufferWriter $out) : void{
        $this->encodeData($out, ProtocolInfo::PROTOCOL_1_26_40);
    }
}
