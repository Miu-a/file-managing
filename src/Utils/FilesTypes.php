<?php
/**
 * Created by PhpStorm.
 * User: radim
 * Date: 21.08.2019
 * Time: 10:39
 */

namespace Optimal\FileManaging\Utils;

final class FilesTypes
{

    // IMAGES
    public const IMAGES_JPG  = ["jpg", "jpeg", "jfif"];
    public const IMAGES_WEBP = ["webp"];
    public const IMAGES_PNG  = ["png"];
    public const IMAGES_GIF  = ["gif"];

    public const BITMAP_IMAGES = ["jfif", "webp", "jpg", "jpeg", "png", "gif"];
    public const VECTOR_IMAGES = ["svg"];
    public const IMAGES        = ["jfif", "webp", "jpg", "jpeg", "png", "gif", "svg"];

    // DOCUMENTS
    public const DOCUMENTS_MS_WORD  = ["doc", "docx"];
    public const DOCUMENTS_MS_EXCEL = ["xls", "xlsx"];
    public const DOCUMENTS_MS_POWER = ["ppt", "pptx"];

    public const DOCUMENTS_MS = ["doc", "docx", "xls", "xlsx", "ppt", "pptx"];

    public const DOCUMENTS_OPEN_WORD  = ["odt", "ott"];
    public const DOCUMENTS_OPEN_EXCEL = ["ods", "sdc"];
    public const DOCUMENTS_OPEN_POWER = ["odp", "sdd"];

    public const DOCUMENTS_OPEN = ["odt", "ott", "ods", "sdc", "odp", "sdd"];

    public const DOCUMENTS_PDF = ["pdf"];

    public const DOCUMENTS = ["doc", "docx", "xls", "xlsx", "ppt", "pptx", "odt", "ott", "ods", "sdc", "odp", "sdd", "pdf"];

    // ARCHIVES
    public const ARCHIVES_ZIP = ["zip"];
    public const ARCHIVES_7Z  = ["7z"];
    public const ARCHIVES_RAR = ["rar"];

    public const ARCHIVES = ["zip", "7z", "rar"];

    // IMPORT, EXPORT

    public const IMP_EXP_CSV  = ["csv"];
    public const IMP_EXP_XML  = ["xml"];
    public const IMP_EXP_JSON = ["json"];

    public const IMP_EXP = ["csv", "xml", "json"];

    // ALL
    public const ALL_SUPPORTED_FILES = [
        "jpg",
        "jfif",
        "webp",
        "jpeg",
        "png",
        "gif",
        "svg",
        "doc",
        "docx",
        "xls",
        "xlsx",
        "ppt",
        "pptx",
        "odt",
        "ott",
        "ods",
        "sdc",
        "odp",
        "sdd",
        "pdf",
        "zip",
        "7z",
        "rar",
        "csv",
        "xml",
        "json",
        "css",
        "js",
        "html"
    ];

    // FILES
    public const NO_IMAGES = [
        "doc",
        "docx",
        "xls",
        "xlsx",
        "ppt",
        "pptx",
        "odt",
        "ott",
        "ods",
        "sdc",
        "odp",
        "sdd",
        "pdf",
        "zip",
        "7z",
        "rar",
        "csv",
        "xml",
        "json",
        "css",
        "js",
        "html"
    ];

    // DISALLOWED
    public const DISALLOWED = [
        "bin",
        "cgi",
        "exe",
        "pl",
        "py",
        "sh",
        "bat",
        "html",
        "xhtml",
        "ico",
        "inc",
        "hphp",
        "module",
        "dll",
        "js",
        "css"
    ];

}