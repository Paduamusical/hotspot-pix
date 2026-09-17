/system script remove [find name="hotspot-pix-pull"]
/system script add name="hotspot-pix-pull" source="/tool fetch url=\"https://hotspot-pix.accesnet.com.br/api/pending.php?format=script\" mode=https dst-path=\"pending-cmds.rsc\"; /import pending-cmds.rsc; /file remove [find name=\"pending-cmds.rsc\"]"
/system scheduler remove [find name="hotspot-pix-pull"]
/system scheduler add name="hotspot-pix-pull" interval=5s on-event="/system script run hotspot-pix-pull" start-time=startup
