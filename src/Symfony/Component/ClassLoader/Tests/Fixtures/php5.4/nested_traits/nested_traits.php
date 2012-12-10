<?php
namespace {
    trait TFooBar2
    {
        use TFooBar;
    }

    trait TFooBar
    {
        use TFooBarBase;
    }

    class CFooBar
    {
        use TFooBar;
    }

    trait TFooBarBase
    {
    }
}
