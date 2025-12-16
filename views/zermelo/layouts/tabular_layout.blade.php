<!doctype html>
<html lang="en">
<head>

    <title>{{ $report->getReportName()  }}</title>

    <link href='{{ asset("vendor/ftrotter/zzermelo/core/font-awesome/css/all.min.css") }}' rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href='{{ $bootstrap_css_location }}' />
    <link rel="stylesheet" type="text/css" href='{{ asset("vendor/ftrotter/zzermelo/core/css/caresetreportengine.report.css") }}' />
    <link rel="stylesheet" type="text/css" href='{{ asset("vendor/ftrotter/zzermelo/zermelobladetabular/datatables/datatables.min.css") }}' />
    <meta name="csrf-token" content="{{ csrf_token() }}">


<!-- inline styles foce the headings on the table to be more dense for smaller columns --> 
<style type="text/css">
.yadcf-filter {
    width: 60px !important;
    max-width: 60px !important;
}

.yadcf-filter-wrapper {
    display: block !important;
}


</style>


</head>
<body>


@include('Zermelo::tabular')

</body>
</html>

