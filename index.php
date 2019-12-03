<?php

/**
 * Demo: Calculate every service row and calculate each rows to sum
 */

$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
include_once(dirname(__FILE__) . '/functions.php');
if (isset($_GET['siteajax'])) {

    $response = (object) [
        'success' => false,
        'data' => false
    ];

    if (isset($_POST['action']) && $_POST['action'] == 'calculate') {

        $list = [];
        $formdata = $_POST['form'] ?? false;
        parse_str($formdata, $form);

        # Results
        $results = (object) [
            'sum_int' => 0,
            'sum_format' => 0,
            'sum_remove' => 0,
            'sum_refund_format' => 0,
            'sum_refund_integer' => 0,
            'products' => [],
            'remove_product_rows' => [],
            'remove_sum' => 0,
            'remove_avg_price' => 0,
            'checkoutValid' => false,
            'formula' => false
        ];

        $sum = 0;

        # Init checkout validation to true
        $checkoutValid = true;
        $products  = [];
        $remove_product_rows = [];
        $remove_sum          = 0;
        $cnt_products = 0;

        $formulas = [
            'add' => [],
            'remove' => []
        ];

        foreach ($form['service_title'] as $i => $value) {
            $product = (object) [
                'title' => $form['service_title'][$i] ?? false,
                'price' => isset($form['service_price'][$i]) ? $form['service_price'][$i] : 0,
                'pct'   => isset($form['service_pct'][$i]) ? $form['service_pct'][$i] : 0,
            ];

            # Common variables
            $unit_price = (float) str_replace(",", ".", $product->price);
            $unit_pct   = (int) $product->pct;

            $product->price = (float) str_replace(",", ".", $product->price);
            $product->pct = intval( $product->pct );

            if( $product->price < 0 )
            {
                $checkoutValid = false;
                $remove_product_rows[] = $product;
                $remove_sum += (round(abs($product->price) * 100)) * $product->pct;

                # Add to formula
                $formulas['remove'][] = "".abs($unit_price)." x ".$unit_pct."";
                continue;
            }
            else
            {
                $product->price = (round($product->price * 100));
  
            }

            if( $product->price > 0 )
            {
                $cnt_products++;
            }

            # Add to formula
            $formulas['add'][] = "".$unit_price." x ".$unit_pct."";

            $sum = $product->price * $product->pct;
            $results->sum_int += intval($sum);

            $products[] = $product;
        }

        # Set new sum with removed prices
        if( $remove_sum > 0 )
        {
            $results->sum_int = $results->sum_int - $remove_sum;
        }

        # Remove prices from products if have any refund rows
        if(!empty($products) && $remove_sum > 0)
        {
            $price_remove_avg =  $remove_sum / $cnt_products;
            $refund_total_price = 0;

            foreach ($products as $key => $product) 
            {
                if( $product->price > 0 )
                {
                    $price = ($product->price - $price_remove_avg);
                    if( $price < 0 )
                    {
                        # TODO: How to calculate negative price right way?
                        $price = 0;
                    }

                    # Round and parse to integer
                    $price = intval($price);

                    $products[$key]->price = $price;
                    $refund_total_price += $price;
                }
 
            }

            if( $refund_total_price == $results->sum_int )
            {
                $checkoutValid = true;
            }

            # Set refunded totals to results
            $results->sum_refund_integer = $refund_total_price;
            $results->sum_refund_format = number_format(((float) $refund_total_price / 100), 2, ",", "");

            $results->remove_avg_price = $price_remove_avg;
        }

        # Get sum format 
        $sum_format = number_format(((float) $results->sum_int / 100), 2, ",", "");

        # Init and create formula
        $formula_str = '';
        if( !empty($formulas) )
        {   
            
            if(!empty($formulas['add']))
            {
                $formula_str .= "(".implode(" + ", $formulas['add']).")";
            }

            if(!empty($formulas['remove']))
            {
                $formula_str .= " - (".implode(" + ", $formulas['remove']).")";
            }


            if( $formula_str )
            {
                $formula_str .= " = " .$sum_format;
            }
        }

        $results->formula = $formula_str;
        $results->checkoutValid = $checkoutValid;
        
        $results->remove_sum = $remove_sum;
        $results->products = $products;
        $results->remove_product_rows = $remove_product_rows;

        $results->sum_format = $sum_format;
        
        $response->data = $results;
        $response->success = true;
    }


    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Checkout</title>
    <link rel="stylesheet" href="style.css" type="text/css">
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
</head>

<body>

    <div class="form">

        <form id="checkout-form" action="<?= $actual_link; ?>" method="post">
            <div class="form-rows">
                <?php
                include(dirname(__FILE__) . '/templates/form-row.php');
                ?>
            </div>

            <input type="submit" name="submit" value="Submit">
            <input type="button" class="add-row" name="add-row" value="Add row">
            <input type="button" class="remove-all-rows" name="remove-all-rows" value="Remove all rows">

            <div class="results">
                <p>Total sum: <span class="total-sum-format">0,00</span>&euro;</p>
                <p>Total sum (in integer): <span class="total-sum-integer">0</span></p>
                <p>Total sum with refunds (format): <span class="total-sum-refunds-format">0</span></p>
                <p>Total sum with refunds (in integer): <span class="total-sum-refunds-integer">0</span></p>
                <p>Checkout validator: <span class="checkout-validator">-</span></p>
                <p>Sum In Formula: <span class="total-sum-in-formula">-</span></p>
            </div>

        </form>

    </div>


    <div id="form-templates">
        <?php
        include(dirname(__FILE__) . '/templates/form-row.php');
        ?>
    </div>


    <script>
        var form = $("#checkout-form");

        $("body").on("click", ".add-row", function(e) {
            e.preventDefault();
            var html = $("#form-templates").find(".service-row").parent().html();
            $(".form-rows").append(html);
        });

        $("body").on("click", ".remove-row", function(e) {
            e.preventDefault();
            $(this).parent().remove();
        });

        $("body").on("click", ".remove-all-rows", function (e) {
            e.preventDefault();
            if( confirm("Are you sure?") === true )
            {
                $(".form-rows").html("");
            }

        });

        $(form).submit(function(e) {
            e.preventDefault();
            var data = $(this).serialize();
            $.ajax({
                type: "POST",
                url: '<?= $actual_link; ?>?siteajax=1',
                data: {
                    form: data,
                    action: 'calculate'
                },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        var results = response.data;

                        // Normal total price in formatted and integer
                        $(".total-sum-format").text(results.sum_format);
                        $(".total-sum-integer").text(results.sum_int);

                        // Re-funded total price in formatted and integer
                        $(".total-sum-refunds-format").text(results.sum_refund_format);
                        $(".total-sum-refunds-integer").text(results.sum_refund_integer);

                        // Formula
                        $(".total-sum-in-formula").text(results.formula);

                        $(".checkout-validator").removeClass('is-valid');
                        $(".checkout-validator").removeClass('is-not-valid');

                        if( results.checkoutValid )
                        {
                            $(".checkout-validator").addClass('is-valid');
                            $(".checkout-validator").text('Yes');
                        }
                        else
                        {
                            $(".checkout-validator").addClass('is-not-valid');
                            $(".checkout-validator").text('No');
                        }

                    }
                }
            });
            return false;
        });
    </script>
</body>

</html>