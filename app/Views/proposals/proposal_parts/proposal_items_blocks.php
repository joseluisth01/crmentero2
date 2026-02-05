<?php
$color = get_setting("proposal_color");
if (!$color) {
    $color = get_setting("invoice_color") ? get_setting("invoice_color") : "#2AA384";
}

$summary_rows = array();
$summary_rows[] = array(
    "label" => app_lang("sub_total"),
    "value" => to_currency($proposal_total_summary->proposal_subtotal, $proposal_total_summary->currency_symbol)
);

if ($proposal_total_summary->discount_total && $proposal_total_summary->discount_type == "before_tax") {
    $summary_rows[] = array(
        "label" => app_lang("discount"),
        "value" => to_currency($proposal_total_summary->discount_total, $proposal_total_summary->currency_symbol)
    );
    $summary_rows[] = array(
        "label" => app_lang("total_after_discount"),
        "value" => to_currency($proposal_total_summary->proposal_subtotal - $proposal_total_summary->discount_total, $proposal_total_summary->currency_symbol)
    );
}

if ($proposal_total_summary->tax) {
    $summary_rows[] = array(
        "label" => $proposal_total_summary->tax_name,
        "value" => to_currency($proposal_total_summary->tax, $proposal_total_summary->currency_symbol)
    );
}

if ($proposal_total_summary->tax2) {
    $summary_rows[] = array(
        "label" => $proposal_total_summary->tax_name2,
        "value" => to_currency($proposal_total_summary->tax2, $proposal_total_summary->currency_symbol)
    );
}

if ($proposal_total_summary->discount_total && $proposal_total_summary->discount_type == "after_tax") {
    $summary_rows[] = array(
        "label" => app_lang("discount"),
        "value" => to_currency($proposal_total_summary->discount_total, $proposal_total_summary->currency_symbol)
    );
}
?>

<div style="width: 100%; margin-top: 10px;">
    <?php foreach ($proposal_items as $item) { ?>
        <div style="border: 1px solid #e7e7e7; border-left: 4px solid <?php echo $color; ?>; background: #fbfbfb; padding: 10px 12px; margin-bottom: 10px;">
            <div style="font-weight: bold; color: #202020; font-size: 11px; margin-bottom: 5px;">
                <?php echo $item->title; ?>
            </div>

            <?php if ($item->description) { ?>
                <div style="color: #666; font-size: 9px; line-height: 1.45; margin-bottom: 8px;">
                    <?php echo custom_nl2br($item->description ? process_images_from_content($item->description) : ""); ?>
                </div>
            <?php } ?>

            <div style="font-size: 9px; color: #444;">
                <strong><?php echo app_lang("quantity"); ?>:</strong> <?php echo $item->quantity . " " . $item->unit_type; ?>
                &nbsp; | &nbsp;
                <strong><?php echo app_lang("rate"); ?>:</strong> <?php echo to_currency($item->rate, $item->currency_symbol); ?>
                &nbsp; | &nbsp;
                <strong><?php echo app_lang("total"); ?>:</strong> <?php echo to_currency($item->total, $item->currency_symbol); ?>
            </div>
        </div>
    <?php } ?>

    <div style="margin-top: 14px; border: 1px solid #e7e7e7; background: #fff; padding: 10px 12px;">
        <?php foreach ($summary_rows as $row) { ?>
            <div style="margin-bottom: 6px; font-size: 10px; color: #333;">
                <span><?php echo $row["label"]; ?></span>
                <span style="float: right;"><?php echo $row["value"]; ?></span>
                <div style="clear: both;"></div>
            </div>
        <?php } ?>

        <div style="margin-top: 10px; padding: 8px 10px; background-color: <?php echo $color; ?>; color: #fff; font-weight: bold; font-size: 11px;">
            <span><?php echo app_lang("total"); ?></span>
            <span style="float: right;"><?php echo to_currency($proposal_total_summary->proposal_total, $proposal_total_summary->currency_symbol); ?></span>
            <div style="clear: both;"></div>
        </div>
    </div>
</div>
