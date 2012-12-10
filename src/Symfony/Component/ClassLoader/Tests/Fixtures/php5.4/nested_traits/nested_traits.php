<?php
namespace {
    trait TFooBarBase
    {
    }

    trait TFooBar
    {
        use TFooBarBase;
    }

    trait TFooBar2
    {
        use TFooBarBase;
    }

    class CFooBar
    {
        use TFooBar;
    }
}
