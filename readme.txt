=== DHL Express live shipping rates and labels ===
Contributors: a2zplugins, hitstacks, hitshipo, hittechmarket
Tags: DHL, DHL Express, dhl for woocommerce, dhl shipping, shipping rates, shipping label,  return label, dhl pickup,  DHL Woocommerce, hittech market, dhl manual, dhl label, dhl rates, dhl price, realtime rates, shipment label of dhl, manual label
Requires at least: 4.0.1
Tested up to: 5.8
Requires PHP: 5.6
Stable tag: 1.0.1
License: GPLv3 or later License
URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

[DHL Express shipping](https://hittechmarket.com/downloads/dhl-express-shipping-for-wordpress/) plugin, integrate the [DHL Express](https://www.dhl.com/en.html) for express delivery in Domestic and Internationally. According to the destination, We are providing all kind of DHL Services. It supports all Countries.
If you need to genrate label for DHL Express then you need to purchase our premium plugin. For premium plugin please visit [DHL Express shipping](https://hittechmarket.com/downloads/dhl-express-shipping-for-wordpress/)


= Useful filters =

1) Filter to restric the country

> add_filter("hit_m_dhlexpress_rate_packages","hit_m_dhlexpress_rate_packages_func",10,1);
> function hit_m_dhlexpress_rate_packages_func($package){
> 	$return_package = array();
> 	if($package['destination']['country'] == 'RU'){
>		return $return_package;
>	}
>	return $package;
> }

2) Flat Rate based on order total for services

> function hit_m_dhlexpress_rate_cost_fnc($rate_cost, $rate_code, $order_total){
>	if($order_total > 250){
>		return 0;
>	}
>	return 20; // Return currency is must to be a DHL confiured currency.
> }
> add_filter("hit_m_dhlexpress_rate_cost", "hit_m_dhlexpress_rate_cost_fnc", 10,3);

3) Change estimated delivery date format or text.

> add_filter('hit_m_dhlexpres_delivery_date', 'hit_dhl_est_delivery_format',10,3);
> function hit_dhl_est_delivery_format($string, $est_date, $est_time){
> 	return $string;
> }

4) To Sort the rates from Lowest to Highest

> add_filter( 'woocommerce_package_rates' , 'hitshipo_sort_shipping_methods', 10, 2 );
> function hitshipo_sort_shipping_methods( $rates, $package ) {
>   if ( empty( $rates ) ) return;
>       if ( ! is_array( $rates ) ) return;
> uasort( $rates, function ( $a, $b ) { 
>   if ( $a == $b ) return 0;
>       return ( $a->cost < $b->cost ) ? -1 : 1; 
>  } );
>       return $rates;
> }

= Your customer will appreciate : =

* The Product is delivered very quickly. The reason is, there this no delay between the order and shipping label action.
* Access to the many services of DHL for domestic & international shipping.
* Good impression of the shop.

= Informations for Configure plugin =

* If you have already a DHL Express Account, please contact your DHL account manager to get your credentials.
* If you are not registered yet, please contact DHL and register and get your API credentials.
* Once you get your credentials, try to setup the plugin and save them.
* Thats it, you are ready to get live shipping rates. 

== NOTE : ==
    To generate shipping label you need to purchase the premium plugin of our [DHL Express shipping](https://hittechmarket.com/downloads/dhl-express-shipping-for-wordpress/)

= Plugin Tags: =

 <blockquote> DHL, DHL Express, dhlexpress,DHL Express shipping, DHL Woocommerce, dhl express for woocommerce, official dhl express, dhl express plugin, dhl plugin, create shipment, dhl shipping, dhl shipping rates, hittech market, dhl manual, dhl label, dhl rates, dhl price, realtime rates, shipment label of dhl, manual label </blockquote>


= About DHL =

   DHL Express is a division of the German logistics company Deutsche Post DHL providing international courier, parcel, and express mail services. Deutsche Post DHL is the world's largest logistics company operating around the world, particularly in sea and air mail

== Note: ==
   All generated labels and invoices are stored inside wp-content / shipping_labels folder.

== Screenshots ==

1. DHL Account integration.
2. Shipper address configuration.
3. Packing algorithm configurations.
4. Shipping rates configuration & shipping services list.
5. Shipping label configuration.

== Changelog ==
	
= 1.0.1 =
*Release Date - 15 July 2021*
	> Minor fixes

= 1.0.0 =
*Release Date - 14 July 2021*
	> Initial Version
