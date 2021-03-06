(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_hr = {
        attach: function (context, settings) {

            $(".top-ico").on("click", function () {
                $('html, body').animate({ scrollTop: 0 }, 'slow');
            });
            
            if (settings.ek_hr.nett) {
                var display = settings.ek_hr.currency + ' ' + addCommas(settings.ek_hr.nett);
                $('#total_display').html(display);
            }

            $(".calculate",context).on("change", function () {
                var total = 0;
                
                //check numeric values
                for ($i = 1; $i < 14; $i++) {
                    var input = $('#custom_aw' + $i).val();
                    if(!$.isNumeric(input)) {
                        $('#erroraw' + $i).html(settings.ek_hr.error);
                    } else {
                        $('#erroraw' + $i).html('');
                    }
                }
                for ($i = 1; $i < 8; $i++) {
                    var input = $('#custom_d' + $i).val();
                    if(!$.isNumeric(input)) {
                        $('#errord' + $i).html(settings.ek_hr.error);
                    } else {
                        $('#errord' + $i).html('');
                    }
                }                
                for ($i = 1; $i < 4; $i++) {
                    var input = $('#fund' + $i +'_employer').val();
                    if(!$.isNumeric(input)) {
                        $('#errorfund' + $i + 'er').html(settings.ek_hr.error);
                    } else {
                        $('#errorfund' + $i + 'er').html('');
                    }
                    var input = $('#fund' + $i +'_employee').val();
                    if(!$.isNumeric(input)) {
                        $('#errorfund' + $i + 'ee').html(settings.ek_hr.error);
                    } else {
                        $('#errorfund' + $i + 'ee').html('');
                    }                    
                }    
                
                var input = $('#income_tax').val();
                    if(!$.isNumeric(input)) {
                        $('#errortaxee').html(settings.ek_hr.error);
                    } else {
                        $('#errortaxee').html('');
                    }
                
                var salary = parseFloat(settings.ek_hr.salary);
                var salary2 = parseFloat(settings.ek_hr.salary2);
                //basic

                var work_base = ($('#work_base').val()) ? parseFloat($('#work_base').val()) : null;
                var unit_work = ($('#unit_work').val()) ? parseFloat($('#unit_work').val()) : 0;
                var leave = ($('#leave').val()) ? parseFloat($('#leave').val()) : 0;
                //var leave = parseFloat($('#leave').val());
                //if(leave == '') leave = 0;

                var thisbasic = salary * (unit_work + leave) / work_base;
                if (thisbasic > salary) {
                    thisbasic = salary
                }

                $('#basic_value').val(thisbasic.toFixed(2));
                total = eval(total + thisbasic);
                //OT
                var overtime_hours = ($('#overtime_hours').val()) ? parseFloat($('#overtime_hours').val()) : 0;
                if (settings.ek_hr.LAF1f != '') {
                    var f = eval(settings.ek_hr.LAF1f);
                    var normal_ot = f.toFixed(2) * overtime_hours;
                } else {
                    var normal_ot = settings.ek_hr.LAF1 * overtime_hours;
                }
                $('#normal_ot').val(normal_ot.toFixed(2));
                total = eval(total + normal_ot);

                var rest_hours = ($('#rest_hours').val()) ? parseFloat($('#rest_hours').val()) : 0;
                if (settings.ek_hr.LAF2f != '') {
                    var f = eval(settings.ek_hr.LAF2f);
                    var rest_day_ot = f.toFixed(2) * rest_hours;
                } else {
                    var rest_day_ot = settings.ek_hr.LAF2 * rest_hours;
                }
                $('#rest_day_ot').val(rest_day_ot.toFixed(2));
                total = eval(total + rest_day_ot);

                var ph_hours = ($('#ph_hours').val()) ? parseFloat($('#ph_hours').val()) : 0;
                if (settings.ek_hr.LAF3f != '') {
                    var f = eval(settings.ek_hr.LAF3f);
                    var ph_ot = f.toFixed(2) * ph_hours;
                } else {
                    var ph_ot = settings.ek_hr.LAF3 * ph_hours;
                }
                $('#ph_ot').val(ph_ot.toFixed(2));
                total = eval(total + ph_ot);

                var mc_days = ($('#mc_days').val()) ? parseFloat($('#mc_days').val()) : 0;
                if (settings.ek_hr.LAF4f != '') {
                    console.log(settings.ek_hr.LAF4f);
                    var f = eval(settings.ek_hr.LAF4f);
                    var mc_day_val = f.toFixed(2) * mc_days;
                } else {
                    var mc_day_val = settings.ek_hr.LAF4 * mc_days;
                }
                $('#mc_day_val').val(mc_day_val.toFixed(2));
                total = eval(total + mc_day_val);

                var x_hours = ($('#x_hours').val()) ? parseFloat($('#x_hours').val()) : 0;
                if (settings.ek_hr.LAF5f != '') {
                    var f = eval(settings.ek_hr.LAF5f);
                    var x_hours_val = f.toFixed(2) * x_hours;
                } else {
                    var x_hours_val = settings.ek_hr.LAF5 * x_hours;
                }
                $('#x_hours_val').val(x_hours_val.toFixed(2));
                total = eval(total + x_hours_val);

                var turnover = ($('#turnover').val()) ? parseFloat($('#turnover').val()) : 0;
                if (settings.ek_hr.LAF6f != '') {
                    var f = eval(settings.ek_hr.LAF6f);
                    var commission = f.toFixed(2) * turnover;
                } else {
                    var commission = settings.ek_hr.LAF6 * turnover;
                }
                $('#commission').val(commission.toFixed(2));
                total = eval(total + commission);

                for ($i = 1; $i < 14; $i++) {

                    var v = eval(settings.ek_hr.custom_a_val[$i]);
                    var f = eval(settings.ek_hr.custom_a_for[$i]);

                    if (v != 0 && v != null) {
                        //console.log('v:' + v);
                        $('#custom_aw' + $i).val(v.toFixed(2));
                        total = eval(total + v);
                    } else if (f != null && typeof (f) != 'undefined') {
                        //console.log('f:' + f);
                        $('#custom_aw' + $i).val(f.toFixed(2));
                        total = eval(total + f);
                    } else if ($('#custom_aw' + $i).val() && $('#custom_aw' + $i).val() != 0) {
                        total = eval(total + parseFloat($('#custom_aw' + $i).val()));
                    } else {
                        $('#custom_aw' + $i).val(0);
                    }
                }

                $('#total_gross').val(total.toFixed(2));

                var deduc = 0;
                var adv = 0;

                if ($('#advance').val() && $('#advance').val() != 0) {
                    var adv = parseFloat($('#advance').val());
                    deduc = adv;
                }

                var less_hours = ($('#less_hours').val()) ? parseFloat($('#less_hours').val()) : 0;
                var less_hours_val = settings.ek_hr.LDF2 * less_hours;
                $('#less_hours_val').val(less_hours_val.toFixed(2));
                deduc = eval(deduc + less_hours_val);

                for ($i = 1; $i < 7; $i++) {

                    var v = eval(settings.ek_hr.custom_d_val[$i]);
                    var f = eval(settings.ek_hr.custom_d_for[$i]);

                    if (v != 0 && v != null) {
                        //console.log('v:' + v);
                        $('#custom_d' + $i).val(v.toFixed(2));
                        deduc = eval(deduc + v);
                    } else if (f != null && typeof (f) != 'undefined') {
                        //console.log('f:' + f);
                        $('#custom_d' + $i).val(f.toFixed(2));
                        deduc = eval(deduc + f);
                    } else if ($('#custom_d' + $i).val() && $('#custom_d' + $i).val() != 0) {
                        deduc = eval(deduc + parseFloat($('#custom_d' + $i).val()));
                    } else {
                        $('#custom_d' + $i).val(0);
                    }


                }

                $('#total_deductions').val(deduc.toFixed(2));

                var total_net = 0
                //funds and tax

//fund 1 
                if (settings.ek_hr.fund1_base == "C")
                    fund_base = settings.ek_hr.salary; // contract basic
                if (settings.ek_hr.fund1_base == "A")
                    fund_base = settings.ek_hr.salary2; // Average basic
                if (settings.ek_hr.fund1_base == "B")
                    fund_base = thisbasic; //calculated basic
                if (settings.ek_hr.fund1_base == "G")
                    fund_base = total;//calculated Gross
                if (settings.ek_hr.fund1_base == "BF")
                    fund_base = eval(thisbasic + normal_ot + rest_day_ot + ph_ot + mc_day_val + x_hours_val);//calculated Gross + fixed allowances
                if (settings.ek_hr.fund1_base == "BMF")
                    fund_base = eval(thisbasic - normal_ot - rest_day_ot - ph_ot - mc_day_val - x_hours_val);//calculated Gross minus OTs
                if (settings.ek_hr.fund1_base == "GMFC")
                    fund_base = eval(total - normal_ot - rest_day_ot - ph_ot - mc_day_val - x_hours_val - commission);//calculated Gross minus OTs and deductions

                if ($('#thisepf').prop('checked')) {

                    if (settings.ek_hr.fund1_calc == 'P') {
                        var f1 = eval(settings.ek_hr.fund1_pc_yer * fund_base / 100);
                        $('#fund1_employer').val(f1.toFixed(2));
                        var f1 = eval(settings.ek_hr.fund1_pc_yee * fund_base / 100);
                        $('#fund1_employee').val(f1.toFixed(2));
                    } else {
                        //extract table data
                        $.ajax({
                            type: "GET",
                            url: drupalSettings.path.baseUrl + 'human-resources/get_table_amount',
                            data: {'coid': settings.ek_hr.coid, 'type': 'fund1', 
                                'value': fund_base, 'field1': 'employer1', 'field2': 'employee1'},
                            async: false,
                            success: function (data) {
                                var str_1 = data.amount1;
                                var str_2 = data.amount2;
                                if (!jQuery.isNumeric(str_1) && str.indexOf("%") > 0) {
                                    //else if return value is % , calculate tax
                                    var rate = str_1.replace('%', '');
                                    var f1 = eval(rate * fund_base / 100);
                                    $('#fund1_employer').val(f1);
                                } else if (jQuery.isNumeric(str_1)) {
                                    //if the return value is double, return value
                                    $('#fund1_employer').val(data.amount1);
                                } else {
                                    $('#fund1_employer').val(0);
                                }
                                if (!jQuery.isNumeric(str_2) && str.indexOf("%") > 0) {
                                    //else if return value is % , calculate tax
                                    var rate = str_2.replace('%', '');
                                    var f1 = eval(rate * fund_base / 100);
                                    $('#fund1_employee').val(f1);
                                } else if (jQuery.isNumeric(str_2)) {
                                    //if the return value is double, return value
                                    $('#fund1_employee').val(data.amount2);
                                } else {
                                    $('#fund1_employee').val(0);
                                }
                            }
                        });

                    }


                } else {

                    if ($('#fund1_employer').val()) {

                    }

                    if ($('#fund1_employee').val()) {
                        var f1 = parseFloat($('#fund1_employee').val());
                    }
                }


//fund 2
                if (settings.ek_hr.fund2_base == "C"){
                    fund_base = settings.ek_hr.salary; // contract basic
                }
                if (settings.ek_hr.fund2_base == "A"){
                    fund_base = settings.ek_hr.salary2; // Average basic
                }
                if (settings.ek_hr.fund2_base == "B"){
                    fund_base = thisbasic; //calculated basic
                }
                if (settings.ek_hr.fund2_base == "G"){
                    fund_base = total;//calculated Gross
                }
                if (settings.ek_hr.fund2_base == "BF"){
                    fund_base = eval(thisbasic + normal_ot + rest_day_ot + ph_ot + mc_day_val + x_hours_val);//calculated Gross + fixed allowances
                }
                if (settings.ek_hr.fund2_base == "BMF"){
                    fund_base = eval(thisbasic - normal_ot - rest_day_ot - ph_ot - mc_day_val - x_hours_val);//calculated Gross minus OTs
                }
                if (settings.ek_hr.fund2_base == "GMFC"){
                    fund_base = eval(total - normal_ot - rest_day_ot - ph_ot - mc_day_val - x_hours_val - commission);//calculated Gross minus OTs and deductions
                }
                if ($('#thissoc').prop('checked')) {
                    //employer
                    if (settings.ek_hr.fund2_calc == 'P') {
                        var f2 = eval(settings.ek_hr.fund2_pc_yer * fund_base / 100);
                        $('#fund2_employer').val(f2.toFixed(2));
                        var f2 = eval(settings.ek_hr.fund2_pc_yee * fund_base / 100);
                        $('#fund2_employee').val(f2.toFixed(2));
                    } else {
                        //extract table data
                        $.ajax({
                            type: "GET",
                            url: drupalSettings.path.baseUrl + 'human-resources/get_table_amount',
                            data: {'coid': settings.ek_hr.coid, 'type': 'fund2', 'value': fund_base, 'field1': 'employer1', 'field2': 'employee1'},
                            async: false,
                            success: function (data) {

                                var str_1 = data.amount1;
                                var str_2 = data.amount2;
                                if (!jQuery.isNumeric(str_1) && str.indexOf("%") > 0) {
                                    //else if return value is % , calculate tax
                                    var rate = str_1.replace('%', '');
                                    var f2 = eval(rate * fund_base / 100);
                                    $('#fund2_employer').val(f2);
                                } else if (jQuery.isNumeric(str_1)) {
                                    //if the return value is double, return value
                                    $('#fund2_employer').val(data.amount1);
                                } else {
                                    $('#fund2_employer').val(0);
                                }
                                if (!jQuery.isNumeric(str_2) && str.indexOf("%") > 0) {
                                    //else if return value is % , calculate tax
                                    var rate = str_2.replace('%', '');
                                    var f2 = eval(rate * fund_base / 100);
                                    $('#fund2_employee').val(f2);
                                } else if (jQuery.isNumeric(str_2)) {
                                    //if the return value is double, return value
                                    $('#fund2_employee').val(data.amount2);
                                } else {
                                    $('#fund2_employee').val(0);
                                }

                            }
                        });

                    }


                } else {

                    if ($('#fund2_employer').val()) {

                    }

                    if ($('#fund2_employee').val()) {
                        var f2 = parseFloat($('#fund2_employee').val());
                    }
                }


//fund 3
                if (settings.ek_hr.fund3_base == "C"){
                    fund_base = settings.ek_hr.salary; // contract basic
                }
                if (settings.ek_hr.fund3_base == "A"){
                    fund_base = settings.ek_hr.salary2; // Average basic
                }
                if (settings.ek_hr.fund3_base == "B"){
                    fund_base = thisbasic; //calculated basic
                }
                if (settings.ek_hr.fund3_base == "G"){
                    fund_base = total;//calculated Gross
                }
                if (settings.ek_hr.fund3_base == "BF"){
                    fund_base = eval(thisbasic + normal_ot + rest_day_ot + ph_ot + mc_day_val + x_hours_val);//calculated Gross + fixed allowances
                }
                if (settings.ek_hr.fund3_base == "BMF"){
                    fund_base = eval(thisbasic - normal_ot - rest_day_ot - ph_ot - mc_day_val - x_hours_val);//calculated Gross minus OTs
                }
                if (settings.ek_hr.fund3_base == "GMFC"){
                    fund_base = eval(total - normal_ot - rest_day_ot - ph_ot - mc_day_val - x_hours_val - commission);//calculated Gross minus OTs and deductions
                }

                if ($('#thiswith').prop('checked')) {
                    //employer
                    if (settings.ek_hr.fund3_calc == 'P') {
                        var f3 = eval(settings.ek_hr.fund3_pc_yer * fund_base / 100);
                        $('#fund3_employer').val(f3.toFixed(2));
                        var f3 = eval(settings.ek_hr.fund3_pc_yee * fund_base / 100);
                        $('#fund3_employee').val(f3.toFixed(2));
                    } else {
                        //extract table data
                        $.ajax({
                            type: "GET",
                            url: drupalSettings.path.baseUrl + 'human-resources/get_table_amount',
                            data: {'coid': settings.ek_hr.coid, 'type': 'fund3', 'value': fund_base, 'field1': 'employer1', 'field2': 'employee1'},
                            async: false,
                            success: function (data) {

                                var str_1 = data.amount1;
                                var str_2 = data.amount2;
                                if (!jQuery.isNumeric(str_1) && str.indexOf("%") > 0) {
                                    //else if return value is % , calculate tax
                                    var rate = str_1.replace('%', '');
                                    var f3 = eval(rate * fund_base / 100);
                                    $('#fund3_employer').val(f3);
                                } else if (jQuery.isNumeric(str_1)) {
                                    //if the return value is double, return value
                                    $('#fund3_employer').val(data.amount1);
                                } else {
                                    $('#fund3_employer').val(0);
                                }
                                if (!jQuery.isNumeric(str_2) && str.indexOf("%") > 0) {
                                    //else if return value is % , calculate tax
                                    var rate = str_2.replace('%', '');
                                    var f3 = eval(rate * fund_base / 100);
                                    $('#fund3_employee').val(f3);
                                } else if (jQuery.isNumeric(str_2)) {
                                    //if the return value is double, return value
                                    $('#fund3_employee').val(data.amount2);
                                } else {
                                    $('#fund3_employee').val(0);
                                }
                            }
                        });
                    }


                } else {

                    if ($('#fund3_employer').val()) {

                    }

                    if ($('#fund3_employee').val()) {
                        var f3 = parseFloat($('#fund3_employee').val());
                    }
                }

//tax
                if (settings.ek_hr.tax_base == "C"){
                    tax_base_val = settings.ek_hr.salary; // contract basic
                }
                if (settings.ek_hr.tax_base == "A"){
                    tax_base_val = settings.ek_hr.salary2; // Average basic
                }
                if (settings.ek_hr.tax_base == "B"){
                    tax_base_val = thisbasic; //calculated basic
                }
                if (settings.ek_hr.tax_base == "G"){
                    tax_base_val = total;//calculated Gross
                }
                if (settings.ek_hr.tax_base == "BF"){
                    tax_base_val = eval(thisbasic + normal_ot + rest_day_ot + ph_ot + mc_day_val + x_hours_val);//Gross + F
                }
                if (settings.ek_hr.tax_base == "BMF"){
                    tax_base_val = eval(thisbasic - normal_ot - rest_day_ot - ph_ot - mc_day_val - x_hours_val);//Gross - F
                }
                if (settings.ek_hr.tax_base == "GMFC"){
                    tax_base_val = eval(total - normal_ot - rest_day_ot - ph_ot - mc_day_val - x_hours_val - commission);
                }
                
                if ($('#thisincometax').prop('checked')) {
                    //adjust with tax option. User can select or unselect amounts (lines) to include in tax
                    //commission
                    var a_tax_adjustment = 0;
                    var d_tax_adjustment = 0; 
                    if ($('#commission-tax').prop('checked')) {
                        a_tax_adjustment = a_tax_adjustment + (parseFloat(jQuery('#commission').val()));
                    }

                    for (i = 1; i < 14; i++) {
                        //adjust with non taxable allowance
                        //if line is checked, the value is added to the calculated
                        if ($('#tax' + i).prop('checked')) {
                            a_tax_adjustment = a_tax_adjustment + (parseFloat(jQuery('#custom_aw' + i).val()));
                        }
                    }

                    for (i = 1; i < 7; i++) {
                        //adjust with non taxable deduction
                        //if line is checked, the value is deducted to the calculated
                        var n = i + 13;
                        if ($('#tax' + n).prop('checked')) {
                            d_tax_adjustment = d_tax_adjustment + (parseFloat(jQuery('#custom_d' + i).val()));
                        }
                    }

                    //employee
                    if (settings.ek_hr.tax_calc == 'P') {
                        var t1 = eval(settings.ek_hr.tax_pc * (tax_base_val + a_tax_adjustment - d_tax_adjustment) / 100);
                        $('#income_tax').val(t1.toFixed(2));
                        $('#incometax_alert').html(settings.ek_hr.tax_pc + "% x " + tax_base_val + " (" + settings.ek_hr.tax_base + ")");
                    } else {
                        //extract table data
                        $.ajax({
                            type: "GET",
                            url: drupalSettings.path.baseUrl + 'human-resources/get_table_amount',
                            data: {'coid': settings.ek_hr.coid, 'eid': settings.ek_hr.eid,'a_tax_adj':a_tax_adjustment,
                                    'd_tax_adj':d_tax_adjustment,'type': 'income_tax','value': tax_base_val,
                                    'ad_category': settings.ek_hr.ad_category,'tax_category': settings.ek_hr.tax_category,
                                    'current_month': settings.ek_hr.current_month,
                                  },
                            async: false,
                            success: function (data) {
                                var str = data.amount1;
                                if (str == 0) {
                                    $('#incometax_alert').html("No value");
                                } else {
                                    if (!jQuery.isNumeric(str) && str.indexOf("%") > 0) {
                                        //else if return value is % , calculate tax
                                        var rate = str.replace('%', '');
                                        var t1 = eval(rate * tax_base_val / 100);
                                        $('#income_tax').val(t1);
                                        $('#incometax_alert').html(rate + "% x " + tax_base_val + " (" + settings.ek_hr.tax_base + ")");
                                    } else if (jQuery.isNumeric(str)) {
                                        //if the return value is double, return value
                                        $('#income_tax').val(data.amount1.toFixed(2));
                                        $('#incometax_alert').html("base: " + tax_base_val + " (" + settings.ek_hr.tax_base + ")");
                                    } else {
                                        $('#income_tax').val(0);
                                        $('#incometax_alert').html("no data");
                                    }
                                }

                            }
                        });

                    }


                } else {
                    $('#incometax_alert').html("");
                    if ($('#income_tax').val()) {
                        var t1 = parseFloat($('#income_tax').val());
                    }
                }

                var f1 = parseFloat($('#fund1_employee').val());
                var f2 = parseFloat($('#fund2_employee').val());
                var f3 = parseFloat($('#fund3_employee').val());
                var t1 = parseFloat($('#income_tax').val());

                total_net = eval(total - deduc - f1 - f2 - f3 - t1);

                $('#total_net').val(total_net.toFixed(2));
                var display = settings.ek_hr.currency + ' ' + addCommas(total_net.toFixed(2));
                $('#total_display').html(display);
            });

        }

    };


    function addCommas(nStr)
    {
        nStr += '';
        x = nStr.split('.');
        x1 = x[0];
        x2 = x.length > 1 ? '.' + x[1] : '';
        var rgx = /(\d+)(\d{3})/;
        while (rgx.test(x1)) {
            x1 = x1.replace(rgx, '$1' + ',' + '$2');
        }
        return x1 + x2;
    }
})(jQuery, Drupal, drupalSettings);

