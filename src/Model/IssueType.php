<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Model;

enum IssueType: string
{
    case MissingTitle = 'missing_title';
    case TitleTooLong = 'title_too_long';
    case MissingDescription = 'missing_description';
    case DescriptionTooLong = 'description_too_long';
    case MissingH1 = 'missing_h1';
    case MultipleH1 = 'multiple_h1';
    case ImageMissingAlt = 'image_missing_alt';
}
