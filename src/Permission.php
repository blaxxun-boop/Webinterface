<?php

namespace ValheimServerUI;

enum Permission {
	case Admin;
	case View_Players;
	case Manage_Players;
	case Manage_Stats;
	case View_Mods;
	case Manage_Mods;
	case View_Users;
	case Manage_Users;
	case View_Lists;
	case Manage_Lists;
	case View_Server;
	case Manage_Server;
}
