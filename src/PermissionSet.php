<?php

namespace ValheimServerUI;

class PermissionSet {
	/**
	 * @param Permission[] $permissions
	 * @param Permission[] $routeMap
	 */
	public function __construct(public array $permissions, public array $routeMap) {}

	public static function read(\SQLite3 $db, ?int $userId, array $routeMap): ?self {
		$permissionList = [];
		if ($userId === null) {
			$group_id = 0;
		} else {
			$stmt = $db->prepare("SELECT group_id FROM user WHERE id = :user_id");
			$stmt->bindValue("user_id", $userId);
			$result = $stmt->execute();
			if (!$user = $result->fetchArray()) {
				return null;
			}

			$group_id = $user["group_id"];
		}

		$stmt = $db->prepare('SELECT permission FROM permission WHERE group_id = :group_id');
		$stmt->bindValue("group_id", $group_id);
		$result = $stmt->execute();
		$reflectionPermissions = new \ReflectionEnum(Permission::class);
		while ($permission = $result->fetchArray()) {
			if ($reflectionPermissions->hasCase($permission["permission"])) {
				$permissionList[] = \constant(Permission::class . "::" . $permission["permission"]);
			}
		}
		return new PermissionSet($permissionList, $routeMap);
	}

	public function allows(Permission $permission) {
		return in_array($permission, $this->permissions) || in_array(Permission::Admin, $this->permissions);
	}

	public function allowsRoute(string $route) {
		if (!isset($this->routeMap[$route])) {
			return true;
		}

		return $this->allows($this->routeMap[$route]);
	}
}
