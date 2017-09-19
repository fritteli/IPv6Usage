<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\IPv6Usage\Columns;

use Piwik\Piwik;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugin\Segment;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;
use Piwik\Tracker\Action;

/**
 * This example dimension counts achievement points for each user. A user gets one achievement point for each action
 * plus five extra achievement points for each conversion. This would allow you to create a ranking showing the most
 * active/valuable users. It is just an example, you can log pretty much everything and even just store any custom
 * request url property. Please note that dimension instances are usually cached during one tracking request so they
 * should be stateless (meaning an instance of this dimension will be reused if requested multiple times).
 *
 * See {@link http://developer.piwik.org/api-reference/Piwik/Plugin\Dimension\VisitDimension} for more information.
 */
class IPVersion extends VisitDimension
{
    /**
     * This will be the name of the column in the log_visit table if a $columnType is specified.
     * @var string
     */
    protected $columnName = 'location_ip_protocol';

    /**
     * If a columnType is defined, we will create this a column in the MySQL table having this type. Please make sure
     * MySQL will understand this type. Once you change the column type the Piwik platform will notify the user to
     * perform an update which can sometimes take a long time so be careful when choosing the correct column type.
     * @var string
     */
    protected $columnType = 'TINYINT(1) NULL';

    /**
     * The name of the dimension which will be visible for instance in the UI of a related report and in the mobile app.
     * @return string
     */
    public function getName()
    {
        return Piwik::translate('IPv6Usage_IPVersion');
    }

    /**
     * The onNewVisit method is triggered when a new visitor is detected. This means here you can define an initial
     * value for this user. By returning boolean false no value will be saved. Once the user makes another action the
     * event "onExistingVisit" is executed. That means for each visitor this method is executed once. If you do not want
     * to perform any action on a new visit you can just remove this method.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed|false
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        if (empty($action)) {
            return 0;
        }

        // Fetch the user's IP address
        $ip = $visitor->getVisitorColumn('location_ip');
        // Check the type of the IP (v4 or v6)
        $protocol = 4;
        $ip = \Piwik\Network\IP::fromBinaryIP($ip);
        if ($ip instanceof \Piwik\Network\IPv6) {
            $protocol = 6;
            #::ffff:0:0/96	ipv4mapped
            #$regex_ipv4map = '/(.f){4}(.0){20}.ip6.arpa$/i'; already detected by Piwik_IP::isIPv4

            #2001::/32	teredo
            $regex_teredo = '/([0-9a-f].){24}0.0.0.0.1.0.0.2.ip6.arpa$/i';

            #2002::/16	6to4
            $regex_6to4 = '/([0-9a-f].){28}2.0.0.2.ip6.arpa$/i';

            $rev_nibbles = $this->reverseIPv6Nibbles($ip);
            if (preg_match($regex_teredo, $rev_nibbles)) {
                $protocol = 101;
            } elseif (preg_match($regex_6to4, $rev_nibbles)) {
                $protocol = 102;
            }
        }
        return $protocol;
    }

    private function reverseIPv6Nibbles($addr)
    {
        #reverse nibbles by Alnitak on http://stackoverflow.com/questions/6619682/convert-ipv6-to-nibble-format-for-ptr-records
        $unpack = unpack('H*hex', $addr);
        $hex = $unpack['hex'];
        $arpa = implode('.', array_reverse(str_split($hex))) . '.ip6.arpa';
        return $arpa;
    }
}
