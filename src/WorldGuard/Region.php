<?php
namespace WorldGuard;

use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\entity\Effect;

class Region {

    private $pos1 = [];
    private $pos2 = [];
    private $levelname = "";
    private $name = "";
    private $flags = [];

    private $vector1;
    private $vector2;
    private $level;
    private $effects;

    public function __construct(string $name, array $pos1, array $pos2, string $level, array $flags)
    {
        $this->name = $name;
        $this->pos1 = $pos1;
        $this->pos2 = $pos2;
        $this->levelname = $level;

        foreach (WorldGuard::FLAGS as $k => $v) {
            if (!isset($flags[$k])) $flags[$k] = $v;
        }
        $this->flags = $flags;

        foreach ($this->flags["effects"] as $id => $amplifier) {
            $this->effects[$id] = Effect::getEffect($id)->setAmplifier($amplifier)->setDuration(999999999);
        }

        $this->vector1 = new Vector3(...$pos1);
        $this->vector2 = new Vector3(...$pos2);
        $this->level = Server::getInstance()->getLevelByName($level);
    }

    public function getPos1() : array
    {
        return $this->pos1;
    }

    public function getPos2() : array
    {
        return $this->pos2;
    }

    public function getVectorPoint1() : Vector3
    {
        return $this->vector1;
    }

    public function getVectorPoint2() : Vector3
    {
        return $this->vector2;
    }

    public function getLevelName() : string
    {
        return $this->levelname;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getLevel() : Level
    {
        return $this->level;
    }

    public function getFlags() : array
    {
        return $this->flags;
    }

    public function getFlag(string $flag)
    {
        return $this->flags[$flag];
    }

    public function setFlag(string $flag, array $avalue)
    {
        $value = $avalue[0];

        if ($flag === "effects") {
            if (!is_numeric($value)) {
                return TF::RED.'Value of "effect" flag must be numeric.';
            }

            if (isset($avalue[1])) {
                if (is_numeric($avalue[1])) {
                    $this->flags["effects"][$value] = $avalue[1];
                    $this->effects[$value] = Effect::getEffect($value)->setAmplifier(--$avalue[1])->setDuration(999999999);
                    return TF::YELLOW.'Added "'.($this->effects[$value])->getName().' '.Utils::getRomanNumber(++$avalue[1]).'" effect to "'.$this->name.'" region.';
                } else {
                    return TF::RED."Amplifier must be numerical.\n".TF::GRAY.'Example: /region flags set '.$this->name.' '.$value.' 1';
                }
            } else {
                $this->flags["effects"][$value] = 0;
                $this->effects[$value] = Effect::getEffect($value)->setAmplifier(0)->setDuration(999999999);
                return TF::YELLOW.'Added "'.($this->effects[$value])->getName().' I" effect to "'.$this->name.'" region.';
            }

            return;
        }

        switch (WorldGuard::FLAG_TYPE[$flag]) {
            case "boolean":
                if ($value !== "true" && $value !== "false") {
                    return TF::RED.'Value of "'.$flag.'" must either be "true" or "false"';
                }
                break;
            case "array":
                if (!is_string($value)) {
                    return TF::RED.'Value of '.$flag.' must be a string.';
                }
                $this->flags[$flag][$value] = "";
                return;
        }

        if ($flag === "notify-enter" || $flag === "notify-leave") {
            $this->flags[$flag] = implode(" ", $avalue);
        } else {
            $this->flags[$flag] = $value;
        }
        return TF::YELLOW.'Flag "'.$flag.'" (of "'.$this->name.'") has been updated to "'.$this->flags[$flag].'".';
    }

    public function resetFlag(string $flag)
    {
        $this->flags[$flag] = WorldGuard::FLAGS[$flag];
    }

    public function getBlockedCmds() : string
    {
        $blocked = $this->flags["blocked-cmds"];
        return empty($blocked) ? "none" : "[".implode(", ", array_keys($blocked))."]";
    }

    public function getAllowedCmds() : string
    {
        $allowed = $this->flags["allowed-cmds"];
        return empty($allowed) ? "none" : "[".implode(", ", array_keys($allowed))."]";
    }

    public function getEffectsString()
    {
        return empty($effects = $this->flags["effects"]) ? "none" : implode(", ", $effects);
    }

    public function getFlagsString(): string
    {
        $array = [];
        foreach ($this->flags as $flag => $value) {
            switch ($flag) {
                case "blocked-cmds":
                    $array[] = $flag.' => '.TF::GRAY.$this->getBlockedCmds();
                    break;
                case "allowed-cmds":
                    $array[] = $flag.' => '.TF::GRAY.$this->getAllowedCmds();
                    break;
                case "effects":
                    $array[] = $flag.' => '.TF::GRAY.$this->getEffectsString();
                    break;
                case "whitelist":
                    $array[] = $flag.' => '.TF::GRAY.$this->getWhitelistString();
                    break;
                default:
                    $array[] = $flag.' => '.TF::GRAY.'"'.$value.'"';
                    break;
            }
        }
        return TF::LIGHT_PURPLE.implode(TF::WHITE.', '.TF::LIGHT_PURPLE, $array);
    }

    public function isCommandAllowed(string $command) : bool
    {
        if (empty($allowed = $this->flags["allowed-cmds"])) {
            if (!empty($blocked = $this->flags["blocked-cmds"])) {
                return !isset($blocked[$command]);
            }
            return true;
        }
        return isset($allowed[$command]);
    }

    public function addToWhitelist(string $playername)
    {
        return isset($this->flags["whitelist"][strtolower($playername)]);
    }

    public function removeFromWhitelist(string $playername)
    {
        unset($this->flags["whitelist"][strtolower($playername)]);
    }

    public function isWhitelisted(Player $player)
    {
        return isset($this->flags["whitelist"][strtolower($player->getName())]);
    }

    public function getWhitelistString() : string
    {
        return implode(", ", array_keys($this->flags["whitelist"]));
    }

    public function getEffects() : array
    {
        return $this->effects;
    }

    public function toArray() : array
    {
        return ["pos1" => $this->pos1, "pos2" => $this->pos2, "level" => $this->levelname, "flags" => $this->flags];
    }
}