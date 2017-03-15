<?php
namespace WorldGuard;

use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerJoinEvent, PlayerMoveEvent, PlayerInteractEvent, PlayerCommandPreprocessEvent, PlayerDropItemEvent};
use pocketmine\utils\TextFormat as TF;
use pocketmine\item\Item;
use pocketmine\event\entity\{EntityDamageEvent, EntityDamageByEntityEvent, EntityExplodeEvent};
use pocketmine\entity\Human;
use pocketmine\event\block\{BlockPlaceEvent, BlockBreakEvent};

class EventListener implements Listener {

    //The reason why item IDs are being used directly, rather than ItemIds::CONSTANTs is for the cross-compatibility amongst forks.

    //These are the items that can be activated with the "use" flag enabled.
    const USABLES = [
        23, 25, 54, 58, 61, 62, 63, 64, 68, 69, 71, 77, 92, 93, 94, 96, 116, 117, 118, 130, 135, 138, 145, 146, 149, 150, 154, 183, 184, 185, 186, 187, 193, 194, 195, 196, 197, 
    ];

    const POTIONS = [
        373, 374, 437, 438, 444
    ];

    private $plugin;

    public function __construct(WorldGuard $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
    * @priority MONITOR
    */
    public function onJoin(PlayerJoinEvent $event)
    {
        $this->plugin->sessionizePlayer($event->getPlayer());
    }

    public function onInteract(PlayerInteractEvent $event)
    {
        if (isset($this->plugin->creating[$id = ($player = $event->getPlayer())->getRawUniqueId()])) {
            if ($event->getAction() === $event::RIGHT_CLICK_BLOCK) {
                $block = $event->getBlock();
                $player->sendMessage(TF::YELLOW.'Selected position: X'.$block->x.', Y: '.$block->y.', Z: '.$block->z.', Level: '.$block->getLevel()->getName());
                $this->plugin->creating[$id][] = [$block->x, $block->y, $block->z, $block->getLevel()->getName()];
                if (count($this->plugin->creating[$id]) >= 2) {
                    if (($reg = $this->plugin->processCreation($player)) !== false) {
                        $player->sendMessage(TF::GREEN.'Successfully created region '.$reg);
                    } else {
                        $player->sendMessage(TF::RED.'An error occurred while creating the region.');
                    }
                }
                $event->setCancelled();
                return;
            }
        }

        if (($reg = $this->plugin->getRegionByPlayer($player)) !== "") {
            if (!$reg->isWhitelisted($player)) {

                if ($reg->getFlag("use") === "false") {
                    if (in_array($event->getBlock()->getId(), self::USABLES)) {
                        $player->sendMessage(TF::RED.'You cannot interact with '.$event->getBlock()->getName().'s.');
                        $event->setCancelled();
                        return;
                    }
                } else $event->setCancelled(false);

                if ($reg->getFlag("potions") === "false") {
                    if (in_array($event->getItem()->getId(), self::POTIONS)) {
                        $player->sendMessage(TF::RED.'You cannot use '.$event->getItem()->getName().' in this area.');
                        $event->setCancelled();
                        return;
                    }
                } else $event->setCancelled(false);

                if ($reg->getFlag("editable") === "false") {
                    if ($event->getItem()->getId() === Item::FLINT_AND_STEEL) {
                        $player->sendMessage(TF::RED.'You cannot use '.$event->getItem()->getName().'.');
                        $event->setCancelled();
                        return;
                    }
                } else $event->setCancelled(false);

            }
            return;
        }
    }

    public function onPlace(BlockPlaceEvent $event)
    {
        if (($region = $this->plugin->getRegionFromPosition($block = $event->getBlock())) !== "") {
            if (!$region->isWhitelisted($player = $event->getPlayer())) {
                if ($region->getFlag("editable") === "false") {
                    $player->sendMessage(TF::RED.'You cannot place blocks in this region.');
                    $event->setCancelled();
                }
            }
        }
    }

    public function onBreak(BlockBreakEvent $event)
    {
        if (($region = $this->plugin->getRegionFromPosition($block = $event->getBlock())) !== "") {
            if (!$region->isWhitelisted($player = $event->getPlayer())) {
                if ($region->getFlag("editable") === "false") {
                    $player->sendMessage(TF::RED.'You cannot break blocks in this region.');
                    $event->setCancelled();
                }
            }
        }
    }

    /**
    * @priority MONITOR
    */
    public function onMove(PlayerMoveEvent $event)
    {
        if (!$event->getFrom()->equals($event->getTo())) {
            if ($this->plugin->updateRegion($player = $event->getPlayer()) !== true) {
                $player->setMotion($event->getFrom()->subtract($player->getLocation())->normalize()->multiply(4));
            }
        }
    }

    public function onHurt(EntityDamageEvent $event)
    {
        if ($event instanceof EntityDamageByEntityEvent) {
            if (($reg = $this->plugin->getRegionByPlayer($event->getPlayer())) !== "") {
                if (!$reg->getFlag("pvp") && ($damager = $event->getDamager())::NETWORK_ID === Human::NETWORK_ID) {
                    $damager->sendMessage(TF::RED.'You cannot hurt players of this region.');
                    $event->setCancelled();
                }
            }
        }
    }

    public function onCommand(PlayerCommandPreprocessEvent $event)
    {
        $cmd = explode(" ", $event->getMessage())[0];
        if (substr($cmd, 0, 1) !== '/') return;
        if (($region = $this->plugin->getRegionByPlayer($player = $event->getPlayer())) !== "" && !$region->isCommandAllowed($cmd)) {
            $player->sendMessage(TF::RED.'You cannot use '.$cmd.' in this area.');
            $event->setCancelled();
        }
    }

    public function onDrop(PlayerDropItemEvent $event)
    {
        if (($reg = $this->plugin->getRegionByPlayer($player =$event->getPlayer())) !== "") {
            if (!$reg->isWhitelisted($player)) {
                if ($reg->getFlag("item-drop") === "false") {
                    $player->sendMessage(TF::RED.'You cannot drop items in this region.');
                    $event->setCancelled();
                    return;
                }
            }
        }
    }

    public function onExplode(EntityExplodeEvent $event)
    {
        foreach ($event->getBlockList() as $block) {
            if (($region = $this->plugin->getRegionFromPosition($block)) !== "") {
                if ($region->getFlag("explosion") === "false") {
                    $event->setCancelled();
                    return;
                }
            }
        }
    }
}