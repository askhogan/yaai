<?php

include_once '/var/www/plugins/callqueue-development/lib/PolycomHandler.php';
if (defined('HOOK') && HOOK) {

    // test = {dial, offhook, incoming, outgoing}
    $xml = ($_REQUEST['test']) ? polycom_listener_get_test_xml() : null;

    // handle the request
    //$listener = new PolycomHandler($xml);
    //$listener->HandleListenerRequest();

    print "Completed request.";

}

function polycom_listener_get_test_xml() {

    if (!$_REQUEST["test"])
        return "";

    $xml = "";
    switch ($_REQUEST["test"]) {
        case "onhook":

            $xml = <<<TEST
<PolycomIPPhone>
<OnHookEvent>
<PhoneIP>192.168.0.0</PhoneIP>
<MACAddress>ffffffffffff</MACAddress>
<TimeStamp>2012-02-18T17:32:32-08:00</TimeStamp>
</OnHookEvent>
</PolycomIPPhone>
TEST;

            break;
        case "offhook":

            $xml = <<<TEST
<PolycomIPPhone>
<OffHookEvent>
<PhoneIP>192.168.0.0</PhoneIP>
<MACAddress>ffffffffffff</MACAddress>
<TimeStamp>2012-02-18T17:32:31-08:00</TimeStamp>
</OffHookEvent>
</PolycomIPPhone>
TEST;

            break;
        case "incoming":

            $xml = <<<TEST
<PolycomIPPhone>
<IncomingCallEvent>
<PhoneIP>192.168.0.107</PhoneIP>
<MACAddress>0004f2313531</MACAddress>
<CallingPartyName>Client</CallingPartyName>
<CallingPartyNumber>sip:14153435295@74.115.98.49</CallingPartyNumber>
<CalledPartyName>7006</CalledPartyName>
<CalledPartyNumber>sip:0004F2313531-14264@s14264.pbxtra.fonality.com</CalledPartyNumber>
<TimeStamp>2012-04-13T22:45:34-08:00</TimeStamp>
</IncomingCallEvent>
</PolycomIPPhone>
TEST;

            break;
        case "outgoing":

            $xml = <<<TEST
<PolycomIPPhone>
<OutgoingCallEvent>
<PhoneIP>192.168.0.103</PhoneIP>
<MACAddress>0004f23130ce</MACAddress>
<CallingPartyName>7011</CallingPartyName>
<CallingPartyNumber>sip:0004F23130CE-14264@s14264.pbxtra.fonality.com</CallingPartyNumber>
<CalledPartyName></CalledPartyName>
<CalledPartyNumber>sip:914153435295@s14264.pbxtra.fonality.com</CalledPartyNumber>
<TimeStamp>2012-04-13T20:38:50-08:00</TimeStamp>
</OutgoingCallEvent>
</PolycomIPPhone>
TEST;

            break;
    }

    return $xml;
}

?>
?>
