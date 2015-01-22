<?php
/* ==============================================================================

Plugin Name: Seo-Lexikon
Plugin URI: http://www.3task.de/tools-programme/wordpress-lexikon/
Description: Dies ist ein Lexikon Plugin mit einer Zusatzfunktion. Kommen in einem Lexikon Beitrag Wörter vor, die schon als Lexikon Eintrag vorhanden sind, verlinkt es automatisch diesen Lexikon Eintrag. Das ist also eine interne Quervlinkung wie es für SEO's manchmal wichtig ist.
Author: <a href="http:/www.3task.de">3task.de</a>
Version: 5.0

/* ============================================================================== */

class seo_lexikon_3task {



	function Enable() {


		if (!class_exists('WPlize'))
			require_once('inc/wplize.class.php');

		add_action('admin_menu', array('seo_lexikon_3task', 'RegisterAdminPage'));
		add_filter('the_content', 'seo_lexikon_contentfilter');
	}

	function int($content){

		$WPlize = new WPlize('seo_lexikon_options');
		$this->items = $WPlize->get_option('ltItems');
		$this->options = $WPlize->get_option('options');

		global $post;
		$this->post_id 		= $post->ID;
		$this->post_parent 	= $post->post_parent;
		$this->content 		= $content;

		if ( is_admin() || !is_array($this->items) ) return false;


		foreach($this->items as $item) {

			if ($this->post_id == $item['ltAddId'] ) {
				$this->content = $this->CreateSummary();
				return;
			}

			if ( $this->options['ltLinking'] == 'selective' && $post->post_parent == $item['ltAddId'] || $this->options['ltLinking'] == 'all' ) {
				$this->CreateLinking($item['ltAddId']);
			}

			if ( $this->post_parent == $item['ltAddId'] && $item['ltAddNav'] == 1 ){
				$this->content = $this->CreateNavigation($item['ltAddId']);
			}

		}

	}

	function RegisterAdminPage() {
		add_submenu_page('options-general.php','Seo-Lexikon','Seo-Lexikon',8,__FILE__,'seo_lexikon_admin');
	}

	function CreateLinking($id){

		$results = seo_lexikon_3task::getResults($id);

		if ($results){

			$GLOBALS["seo_lexikon_replace_num"] = 0;
			$GLOBALS["seo_lexikon_replace_array"] = array();

			foreach($results as $result) {

				#$s = trim(ent2ncr(htmlentities(utf8_decode($result['post_title']))));
				$s = trim($result['post_title']);
				$pi = $result["ID"];
				$link = get_the_permalink( $pi );

				$GLOBALS["r"] = $result['ID'];
				$GLOBALS["s"] = $result['post_title'];

				$this->content = preg_replace("~(?![^<]*>)(?!<h[1-6][^>])(?!<a.*>)(\b$s\b)(?!.*<\/h[1-6]>)(?!.*<\/a>)~", "<a href='$link'>$s</a>", $this->content);


				#(\S+)=["']?((?:.(?!["']?\s+(?:\S+)=|[>"']))+.)["']?

				// $this->content = preg_replace_callback	(
				// 										'~(?![^<]*>)([^a-zA-ZüöäÜÖÄ\-])(?!<h[1-6][^>]*)(?!<a[^>]*)(?!<img[^\/>]*)('.$s.')(?!.*\/>)(?!.*<\/a>)(?!.*<\/h[1-6]>)([^a-zA-ZüöäÜÖÄ\-])~i',
				// 										array('seo_lexikon_3task', 'replace_callback'),
				// 										$this->content,
				// 										1
				// 										);

			}

			if ($GLOBALS["seo_lexikon_replace_num"] > 0) {
				foreach($GLOBALS["seo_lexikon_replace_array"] as $key => $value){
					$this->content = str_replace('[lexikonflag]'.$key.'[/lexikonflag]', $value, $this->content);
				}
			}
		}



	}

	function CreateNavigation($parent_id){

		$GetAlphabeticList = seo_lexikon_3task::GetAlphabeticList($parent_id);

		if ($GetAlphabeticList){

			$output = null;

			$output = "<div class='AlphabeticList'>";

			$premalink = get_permalink($parent_id);

			foreach ($GetAlphabeticList as $initial => $group)
				if ( $group ) { $output .= "<a href='".$premalink."#".$initial."'>".$initial."</a> "; }

			$output .= "</div>";

		}

		return $output.$this->content;

	}

	function CreateSummary(){

		$GetAlphabeticList = seo_lexikon_3task::GetAlphabeticList($this->post_id);

		if ($GetAlphabeticList) {
			$output = null;

			$output = "<div class='AlphabeticList'>";

			foreach ($GetAlphabeticList as $initial => $group)
				if ( $group ) { $output .= "<a href='#".$initial."'>".$initial."</a> "; }

			$output .= "</div>";

			foreach ($GetAlphabeticList as $initial => $group) {

				if ( $group )
					$output .= "<h2 class='initial' id='$initial'>".$initial."</h2>";

				for ($i = 0, $x = count($group); $i < $x; ++$i) {
					$output .= "<a href='".$group[$i]['post_url']."'>".$group[$i]['post_title']."</a><br />";
				}

			}
		}
		return $this->content.$output;
	}

	function replace_callback($match){
		$GLOBALS["seo_lexikon_replace_array"][$GLOBALS["s"]] = '<a href="'.get_permalink($GLOBALS["r"]).'" title="'.$GLOBALS["s"].'">'.$GLOBALS["s"].'</a>';
		$GLOBALS["seo_lexikon_replace_num"]++;
		return $match[1].'[lexikonflag]'.$GLOBALS["s"].'[/lexikonflag]'.$match[3];
	}

	function getResults($id) {
		global $wpdb,$post;
		return $wpdb->get_results("SELECT post_title,post_name,ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'page' AND ID <> $post->ID AND post_parent = '".$id."' ORDER BY post_title",ARRAY_A);;
	}

	function getInitial($string) {

		$string = trim($string);

		$chars['feed'] = array(
			'&#196;', '&#228;', '&#214;', '&#246;', '&#220;', '&#252;', '&#223;'
			);

		$chars['chars'] = array(
			'Ä', 'ä', 'Ö', 'ö', 'Ü', 'ü', 'ß;'
			);

		$chars['perma'] = array(
			'Ae', 'ae', 'Oe', 'oe', 'Ue', 'ue', 'ss'
			);

		$string = str_replace($chars['feed'],$chars['perma'],$string);
		$string = str_replace($chars['chars'],$chars['perma'],$string);

		$initial = $string{0};

		if (preg_match('/^[a-z]$/i', $initial))
			return strtoupper($initial);


		switch ($initial) {
			case 'ä': case 'Ä':
			return 'A';
			case 'ö': case 'Ö':
			return 'O';
			case 'ü': case 'Ü':
			return 'U';
			default:
			return '#';
		}
	}

	function GetAlphabeticList($postID){

		global $wpdb;

		$results = seo_lexikon_3task::getResults($postID);

		if (!$results) return;

		$keys = range('A', 'Z');
		array_unshift($keys, "#");
		$values = array_fill(0, 27, array());
		$data = array_combine($keys, $values);


		foreach ($results as $result) {
			$initial = seo_lexikon_3task::getInitial($result['post_title']);
			$result['post_url'] = get_permalink($result['ID']);
			$data[$initial][] = $result;
		}

		return $data;
	}

}

function seo_lexikon_admin() {

	?><div class="wrap">
	<div id="icon-tools" class="icon32"><br></div>
	<h2>Lexikon</h2>

	<?php

	if ( !class_exists('WPlize') ) {
		require_once('inc/wplize.class.php');
	}


	$WPlize = new WPlize('seo_lexikon_options');

	if ( isset($_POST['ltSubmitOptions']) ) {

		$options = array('ltLinking');

		foreach($options as $option){
			$options[$option] = stripslashes(htmlspecialchars($_POST[$option]));
		}

		$WPlize->update_option('options',$options);
		define("LTNOTICE", "Allgemeine Einstellungen gespeichert!");

	}

	if ( isset($_POST['ltSubmitAdd']) ) {

		$items = $WPlize->get_option('ltItems');

		if (!is_array($items)) $items = array();

		$options = array('ltAddId','ltAddNav');

		foreach($options as $option){
			$options[$option] = stripslashes(htmlspecialchars($_POST[$option]));
		}

		$items[] = $options;

		$WPlize->update_option('ltItems',$items);
		define("LTNOTICE", "Lexikon wurde hinzugefügt!");

	}

	if ( isset($_POST['ltSubmitEdit']) ) {

		$items = array();

		if (is_array($_POST['ltAddId'])){

			foreach($_POST['ltAddId'] as $key => $value){
				$items[$key]['ltAddId'] = stripslashes(htmlspecialchars($_POST['ltAddId'][$key]));
				if (!isset($_POST['ltAddNav'][$key])){
					$_POST['ltAddNav'][$key] = 0;
				}

				$items[$key]['ltAddNav'] = stripslashes(htmlspecialchars($_POST['ltAddNav'][$key]));

			}

		}

		$WPlize->update_option('ltItems',$items);
		define("LTNOTICE", "Einstellungen gespeichert!");

	}

	?>

	<?php

		# Statusausgabe
	if (defined('LTNOTICE')) { echo "<div class='updated'><p><strong>".LTNOTICE."</strong></p></div>"; }

	$items = $WPlize->get_option('ltItems');

	if (is_array($items) && count($items) > 0) {

		?>

		<script type="text/javascript">
			jQuery(document).ready(function(){

				jQuery('a.delete').live('click',function(){
					jQuery('p.' + jQuery(this).attr('rel')).fadeTo(500, '0.5',function(){ jQuery(this).remove(); });
					return false;
				});

			});
		</script>

		<div style="padding: 4px 18px 18px 18px; background:#fffffff; border: 1px solid #cccccc; -moz-border-radius: 4px; margin: 13px 0 0 0;">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<h3>Lexikon bearbeiten</h3>

				<?php foreach($items as $key => $item) { ?>

				<p class="row_<?php echo $key; ?>">
					<label>Übersichtseite:</label>
					<?php
					echo $pages = wp_dropdown_pages(
						array('post_type' => 'page',
							'selected' => $item["ltAddId"],
							'name' => 'ltAddId[]',
							'show_option_none' => __('(no parent)'),
							'sort_column'=> 'menu_order, post_title',
							'echo' => 0
							)
						);
						?>

						<label>A-Z Navigation:</label>
						<select name="ltAddNav[]">
							<option value="1" <?php if ( $item["ltAddNav"] == 1 ) { ?>selected="selected"<?php } ?>>aktiviert</option>
							<option value="2" <?php if ( $item["ltAddNav"] == 2 ) { ?>selected="selected"<?php } ?>>deaktiviert</option>
						</select>

						<a href="#" class="delete" rel="row_<?php echo $key; ?>">Löschen</a>
					</p>

					<?php } ?>

					<hr style="border:none; background:none; border-top: 1px solid #ccc; " />

					<p class="submit" style="padding: 5px 0 0 0;">
						<input name="ltSubmitEdit" class="button-primary" value="Änderungen speichern" type="submit">
					</p>
				</form>
			</div>

			<?php } ?>

			<?php if (!count($items) > 0) { ?>


			<div style="padding: 4px 18px 18px 18px; background:#fffffff; border: 1px solid #cccccc; -moz-border-radius: 4px; margin: 13px 0 0 0;">
				<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
					<h3>Lexikon hinzufügen</h3>

					<p>
						<label>Lexikon Übersichtsseite:</label>
						<?php
						echo $pages = wp_dropdown_pages(
							array('post_type' => 'page',
								'selected' => null,
								'name' => 'ltAddId',
								'show_option_none' => __('(no parent)'),
								'sort_column'=> 'menu_order, post_title',
								'echo' => 0
								)
							);
							?>
						</p>

						<p>
							<input name="ltAddNav" type="checkbox" value="1"  /> Lexikoneinträge mit A-Z Navigation anzeigen.
						</p>

						<hr style="border:none; background:none; border-top: 1px solid #cccccc; " />

						<p class="submit" style="padding: 5px 0 0 0;">
							<input name="ltSubmitAdd" class="button-primary" value="Lexikon erstellen" type="submit">
						</p>
					</form>
				</div>

				<?php } ?>

				<div style="padding: 4px 18px 18px 18px; background:#ffffff; border: 1px solid #cccccc; -moz-border-radius: 4px; margin: 13px 0 0 0;">
					<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
						<h3>Allgemeine Einstellungen</h3>
						<?php $options = $WPlize->get_option('options'); ?>
						<p>
							<label>Interne Verlinkung:</label>
							<select name="ltLinking">
								<option value="null">deaktiviert</option>
								<!-- <option value="all" <?php if ( $options['ltLinking'] == 'all' ) { ?>selected="selected"<?php } ?>>komplette Seite</option> -->
								<option value="selective" <?php if ( $options['ltLinking'] == 'selective' ) { ?>selected="selected"<?php } ?>>nur innerhalb des Lexikas</option>
							</select>
						</p>

						<hr style="border:none; background:none; border-top: 1px solid #ccc; " />

						<p class="submit" style="padding: 5px 0 0 0;">
							<input name="ltSubmitOptions" class="button-primary" value="Änderungen speichern" type="submit">
						</p>
					</form>
				</div>
			</div><?php

		}

		function seo_lexikon_contentfilter($content) {
			$lexikon = new seo_lexikon_3task();
			$lexikon->int($content);
			return $lexikon->content;
		}


		if(defined('ABSPATH')){
			$lexikon = new seo_lexikon_3task();
			$lexikon->Enable();
		}