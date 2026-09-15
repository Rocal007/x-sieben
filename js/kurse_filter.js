jQuery(document).ready(function ($) {
    let themeCat = [
        '90-trend-themen-2018',
        'agile-coach-resilienz-ausbildung',
        'agile-projekte-ipma-scrum',
        '10-betriebswirtschaft-management',
        '40-ebcl-certified-manager-ausbildung',
        '80-mba-einkauf-logistik_bmoe_kooperation',
        'e-learning',
        '79-trainerinnen_ausbildung_iso_17024fied-manager-ausbildung-engl',
        '50-persoenlichkeitsbildung',
        '60-online-marketing-akademie',
        'agile-welt-new-work'
    ];
    let skillCat = [
        'einsteiger',
        'fortgeschrittener',
        'lehrgang',
        'seminare',
        'crashkurs'
    ];
   
    $('input[type=radio][name=radios]').change(function () {
        if (this.value == '90-trend-themen-2018') {
            $('#90-trend-themen-2018-indicator, #90-trend-themen-2018-full').addClass('show-it');
            $('#90-trend-themen-2018-indicator, #90-trend-themen-2018-full').removeClass('hide-it');
        }
        else {
            $('#90-trend-themen-2018-indicator, #90-trend-themen-2018-full').addClass('hide-it');
            $('#90-trend-themen-2018-indicator, #90-trend-themen-2018-full').removeClass('show-it');
        }
    }
    );
    $('input[type=radio][name=radios]').change(function () {
        if (this.value == 'agile-coach-resilienz-ausbildung') {
            $('#agile-coach-resilienz-ausbildung-indicator, #agile-coach-resilienz-ausbildung-full').addClass('show-it');
            $('#agile-coach-resilienz-ausbildung-indicator, #agile-coach-resilienz-ausbildung-full').removeClass('hide-it');
        }
        else {
            $('#agile-coach-resilienz-ausbildung-indicator, #agile-coach-resilienz-ausbildung-full').addClass('hide-it');
            $('#agile-coach-resilienz-ausbildung-indicator, #agile-coach-resilienz-ausbildung-full').removeClass('show-it');
        }
    }
    );
    $('input[type=radio][name=radios]').change(function () {
        if (this.value == 'agile-projekte-ipma-scrum') {
            $('#agile-projekte-ipma-scrum-indicator, #agile-projekte-ipma-scrum-full').addClass('show-it');
            $('#agile-projekte-ipma-scrum-indicator, #agile-projekte-ipma-scrum-full').removeClass('hide-it');
        }
        else {
            $('#agile-projekte-ipma-scrum-indicator, #agile-projekte-ipma-scrum-full').addClass('hide-it');
            $('#agile-projekte-ipma-scrum-indicator, #agile-projekte-ipma-scrum-full').removeClass('show-it');
        }
    }
    );
    $('input[type=radio][name=radios]').change(function () {
        if (this.value == '60-online-marketing-akademie') {
            $('#60-online-marketing-akademie-indicator, #60-online-marketing-akademie-full').addClass('show-it');
            $('#60-online-marketing-akademie-indicator, #60-online-marketing-akademie-full').removeClass('hide-it');
        }
        else {
            $('#60-online-marketing-akademie-indicator, #60-online-marketing-akademie-full').addClass('hide-it');
            $('#60-online-marketing-akademie-indicator, #60-online-marketing-akademie-full').removeClass('show-it');
        }
    }
    );
    $('input[type=radio][name=radios]').change(function () {
        if (this.value == '10-betriebswirtschaft-management') {
            $('#10-betriebswirtschaft-management-indicator, #10-betriebswirtschaft-management-full').addClass('show-it');
            $('#10-betriebswirtschaft-management-indicator, #10-betriebswirtschaft-management-full').removeClass('hide-it');
        }
        else {
            $('#10-betriebswirtschaft-management-indicator, #10-betriebswirtschaft-management-full').addClass('hide-it');
            $('#10-betriebswirtschaft-management-indicator, #10-betriebswirtschaft-management-full').removeClass('show-it');
        }
    }
    );
    $('input[type=radio][name=radios]').change(function () {
        if (this.value == '40-ebcl-certified-manager-ausbildung') {
            $('#40-ebcl-certified-manager-ausbildung-indicator,#40-ebcl-certified-manager-ausbildung-full').addClass('show-it');
            $('#40-ebcl-certified-manager-ausbildung-indicator,#40-ebcl-certified-manager-ausbildung-full').removeClass('hide-it');
        }
        else {
            $('#40-ebcl-certified-manager-ausbildung-indicator,#40-ebcl-certified-manager-ausbildung-full').addClass('hide-it');
            $('#40-ebcl-certified-manager-ausbildung-indicator,#40-ebcl-certified-manager-ausbildung-full').removeClass('show-it');
        }
    }
    );
    $('input[type=radio][name=radios]').change(function () {
        if (this.value == '80-mba-einkauf-logistik_bmoe_kooperation') {
            $('#80-mba-einkauf-logistik_bmoe_kooperation-indicator,#80-mba-einkauf-logistik_bmoe_kooperation-full').addClass('show-it');
            $('#80-mba-einkauf-logistik_bmoe_kooperation-indicator,#80-mba-einkauf-logistik_bmoe_kooperation-full').removeClass('hide-it');
        }
        else {
            $('#80-mba-einkauf-logistik_bmoe_kooperation-indicator,#80-mba-einkauf-logistik_bmoe_kooperation-full').addClass('hide-it');
            $('#80-mba-einkauf-logistik_bmoe_kooperation-indicator,#80-mba-einkauf-logistik_bmoe_kooperation-full').removeClass('show-it');
        }
    }
    );
    $('input[type=radio][name=radios]').change(function () {
        if (this.value == 'e-learning') {
            $('#e-learning-indicator, #e-learning-full').addClass('show-it');
            $('#e-learning-indicator, #e-learning-full').removeClass('hide-it');
        }
        else {
            $('#e-learning-indicator, #e-learning-full').addClass('hide-it');
            $('#e-learning-indicator, #e-learning-full').removeClass('show-it');
        }
    }
    );
    $('input[type=radio][name=radios]').change(function () {
        if (this.value == '79-trainerinnen_ausbildung_iso_17024fied-manager-ausbildung-engl') {
            $('#79-trainerinnen_ausbildung_iso_17024fied-manager-ausbildung-engl-indicator, #79-trainerinnen_ausbildung_iso_17024fied-manager-ausbildung-engl-full').addClass('show-it');
            $('#79-trainerinnen_ausbildung_iso_17024fied-manager-ausbildung-engl-indicator, #79-trainerinnen_ausbildung_iso_17024fied-manager-ausbildung-engl-full').removeClass('hide-it');
        }
        else {
            $('#79-trainerinnen_ausbildung_iso_17024fied-manager-ausbildung-engl-indicator, #79-trainerinnen_ausbildung_iso_17024fied-manager-ausbildung-engl-full').addClass('hide-it');
            $('#79-trainerinnen_ausbildung_iso_17024fied-manager-ausbildung-engl-indicator, #79-trainerinnen_ausbildung_iso_17024fied-manager-ausbildung-engl-full').removeClass('show-it');
        }
    }
    );
    $('input[type=radio][name=radios]').change(function () {
        if (this.value == '50-persoenlichkeitsbildung') {
            $('#50-persoenlichkeitsbildung-indicator, #50-persoenlichkeitsbildung-full').addClass('show-it');
            $('#50-persoenlichkeitsbildung-indicator, #50-persoenlichkeitsbildung-full').removeClass('hide-it');
        }
        else {
            $('#50-persoenlichkeitsbildung-indicator, #50-persoenlichkeitsbildung-full').addClass('hide-it');
            $('#50-persoenlichkeitsbildung-indicator, #50-persoenlichkeitsbildung-full').removeClass('show-it');
        }
    }
    );
  $('input[type=radio][name=radios]').change(function () {
        if (this.value == 'agile-welt-new-work') {
            $('#agile-welt-new-work-indicator, #agile-welt-new-work').addClass('show-it');
            $('#agile-welt-new-work-indicator, #agile-welt-new-work').removeClass('hide-it');
        }
        else {
            $('#agile-welt-new-work-indicator, #agile-welt-new-work').addClass('hide-it');
            $('#agile-welt-new-work-indicator, #agile-welt-new-workl').removeClass('show-it');
        }
    }
    );
  
    //Breadcrumbs back funktion----------------------------------------------------------------------------------
    $.urlParam = function (name) {
        var results = new RegExp('[\?&]' + name + '=([^^&#]*)').exec(window.location.href);
        if (results == null) {
            return null;
        }
        else {
            return results[1] || 0;
        }
    }
    let $cat = '';
    if ($.urlParam('coursecat') == null) {
        $cat = "90-trend-themen-2018"
    }
    else {
        $cat = $.urlParam('coursecat');
    };
    $("input[name='radios']").each(function (index, elem) {
        let $radio = $(elem);
        let $indicator = '';
        let $full = '';
        $('#' + $cat + '-full').addClass('show-it');
        if ($radio[0].id == $cat) {
            $indicator = '#' + $cat + '-indicator';
            $full = '#' + $cat + '-full';
            $(this).prop('checked', true);
            $($indicator).addClass('show-it');
            $($full).addClass('show-it');
            $($indicator).removeClass('hide-it');
            $($full).removeClass('hide-it');
        }
        else {
            $indicator = '#' + $radio[0].id + '-indicator';
            $full = '#' + $radio[0].id + '-full';
            $(this).prop('checked', false);
            $($indicator).addClass('hide-it');
            $($full).addClass('hide-it');
            $($indicator).removeClass('show-it');
            $($full).removeClass('show-it');
        }
    }
    );
    //Course Types Radio Handler                                       
    $("input[name='course-type']").each(function (index, elem, value) {
        let $courseTypes = $(elem);
        let $indicator = '';
        let $class = '';
        $indicator = '#' + $courseTypes[0].id + '-indicator';
        $($indicator).addClass('hide-it');
        $("input[name='course-type']").change(function (elem, value) {
            $class = '.' + $courseTypes[0].id;
            //console.log($class);
            if (this.value == $courseTypes[0].id) {
                //console.log('yes');
                $($indicator).addClass('show-it');
                $($indicator).removeClass('hide-it');
                $($class).addClass('show-it');
                $($class).removeClass('hide-it');
            }
            else {
                //console.log('no');
                $($indicator).addClass('hide-it');
                $($indicator).removeClass('show-it');
                $($class).addClass('hide-it');
                $($class).removeClass('show-it');
            };
        }
        );
    }
    );
    //Course Types Radio Handler non selected     
    document.querySelectorAll("input[name='course-type']").forEach(function (elem, value, index) {
        elem.addEventListener("mousedown", function () {
            let $courseTypes = $(elem);
            if (this.checked) {
                this.onclick = function () {
                    this.checked = false;
                    let $selector = $("input[name='course-type']");
                    $selector.each(function (index) {
                        let $classNoSection = "." + $selector[index].id;
                        console.log($classNoSection);
                        $($classNoSection).addClass('show-it');
                        $($classNoSection).removeClass('hide-it');
                    }
                    ),
                        $indicator = '#' + $courseTypes[0].id + '-indicator';
                    $($indicator).addClass('hide-it');
                }
            }
            else {
                this.onclick = null
            }
        }
        )
    }
    );
    //Skills radio Handler                                       
    $("input[name='skills']").each(function (index, elem) {
        let $skills = $(elem);
        let $indicator = '';
        let $class = '';
        $('#' + $skills[0].id + '-indicator').addClass('hide-it');
        $indicator = '#' + $skills[0].id + '-indicator';
        $("input[name='skills']").change(function (elem, value) {
            $class = '.' + $skills[0].id;
            console.log($class);
            if (this.value == $skills[0].id) {
                console.log('yes');
                $($indicator).addClass('show-it');
                $($indicator).removeClass('hide-it');
                $($class).addClass('show-it');
                $($class).removeClass('hide-it');
            }
            else {
                console.log('no');
                $($indicator).addClass('hide-it');
                $($indicator).removeClass('show-it');
                $($class).addClass('hide-it');
                $($class).removeClass('show-it');
            };
        }
        );
    }
    );
   //Skills Radio Handler non selected     
    document.querySelectorAll("input[name='skills']").forEach(function (elem, value, index) {
        elem.addEventListener("mousedown", function () {
            let $courseTypes = $(elem);
            if (this.checked) {
                this.onclick = function () {
                    this.checked = false;
                    let $selector = $("input[name='skills']");
                    $selector.each(function (index) {
                        let $classNoSectionSkills = "." + $selector[index].id;
                        console.log($classNoSectionSkills);
                        $($classNoSectionSkills).addClass('show-it');
                        $($classNoSectionSkills).removeClass('hide-it');
                    }
                    ),
                        $indicator = '#' + $courseTypes[0].id + '-indicator';
                        $($indicator).addClass('hide-it');
                }
            }
            else {
                this.onclick = null
            }
        }
        )
    }
    )
  
  
}
);

