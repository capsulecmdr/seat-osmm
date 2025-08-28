{{-- @extends('web::layouts.app')

@section('title', 'Maintenance')

@section('content') --}}

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Metro UI Demo</title>
    <link rel="stylesheet" href="https://cdn.metroui.org.ua/current/metro.css">
    <link rel="stylesheet" href="https://cdn.metroui.org.ua/current/icons.css">
    <style>
        .navview-content {
            overflow: hidden !important;
        }

        .cog {
            position: absolute;
            z-index: -1;
            color: #fefefe;
        }

        #cog1 {
            top: 20px;
            left: 20px;
            font-size: 244px;
        }

        #cog2 {
            top: 180px;
            left: 180px;
            font-size: 244px;

        }

        #cog3,
        #cog4 {
            top: 20px;
            right: 80px;
            font-size: 144px;
        }

        #cog4 {
            top: auto;
            bottom: 20px;
            left: 100px;
            right: auto;
        }

        #cog5 {
            font-size: 80px;
            right: 120px;
            bottom: 200px;
        }

        #cog1,
        #cog2 {
            span {
                animation-duration: 5s !important;
            }
        }

        #cog3 {
            span {
                animation-duration: 7s !important;
            }
        }

        #cog4 {
            span {
                animation-duration: 15s !important;
            }
        }

        #cog4 {
            span {
                animation-duration: 10s !important;
            }
        }

        .dark-side {
            .cog {
                color: #212225 !important;
            }
        }
    </style>
</head>

<body class="h-vh-100 d-flex flex-column flex-justify-center flex-align-center">
    <div id="root" class="h-100 d-flex flex-center flex-column">
        <div class="h-100 d-flex flex-column flex-center">
            <div class="display4">
                <span class="mif-under-construction"></span>
            </div>
            <h2 class="text-center">This page is under construction!</h2>
            <div class="row flex-justify-content-center">
                <div class="text-leader2 text-center cell-md-6">
                    Please visit it again later.
                </div>
            </div>
            <div class="w-75 mt-10">
                <div class="row">
                    <div class="cell-ld-4">
                        <div class="box">
                            <div class="box-title">Title 1</div>
                            <div>
                                <p>
                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam in dui mauris.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="cell-ld-4">
                        <div class="box">
                            <div class="box-title">Title 2</div>
                            <div>
                                <p>
                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam in dui mauris.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="cell-ld-4">
                        <div class="box">
                            <div class="box-title">Title 3</div>
                            <div>
                                <p>
                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam in dui mauris.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-10">
                <a href="/" class="button alert"><span class="mif-arrow-left mr-2"></span> Back to Home</a>
            </div>
            <div class="cog" id="cog1">
                <span class="mif-cog ani-spin d-block"></span>
            </div>
            <div class="cog" id="cog2">
                <span class="mif-cog ani-spin-reverse d-block"></span>
            </div>
            <div class="cog" id="cog3">
                <span class="mif-cog ani-spin d-block"></span>
            </div>
            <div class="cog" id="cog4">
                <span class="mif-cog ani-spin-reverse d-block"></span>
            </div>
            <div class="cog" id="cog5">
                <span class="mif-cog ani-spin-reverse d-block"></span>
            </div>
        </div>
        <script type="text/javascript">window.PAGE_TITLE = 'Under Construction'</script>
    </div>
    <script src="https://cdn.metroui.org.ua/current/metro.js"></script>
    <script>
        var app = new Model({
            counter: 0,
            status() {
                return this.counter === 0
                    ? "Zero"
                    : this.counter > 0
                        ? "Positive"
                        : "Negative";
            }
        });

        app.init("#root");
    </script>
</body>

</html>



{{-- @endsection --}}