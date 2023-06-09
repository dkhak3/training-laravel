re being cached.
### If the repository is being stored on a RAID, the block size should be
### either 50% or 100% of RAID block size / granularity.  Also, your file
### system blocks/clusters should be properly aligned and sized.  In that
### setup, each access will hit only one disk (minimizes I/O load) but
### uses all the data provided by the disk in a single access.
### For SSD-based storage systems, slightly lower values around 16 kB
### may improve latency while still maximizing throughput.  If block-read
### has not been enabled, this will be capped to 4 kBytes.
### Can be changed at any time but must be a power of 2.
### block-size is given in kBytes and with a default of 64 kBytes.
# block-size = 64
###
### The log-to-phys index maps data item numbers to offsets within the
### rev or pack file.  This index is organized in pages of a fixed maximum
### capacity.  To access an item, the page table and the respective page
### must be read.
### This parameter only affects revisions with thousands of changed paths.
### If you have several extremely large revisions (~1 mio changes), think
### about increasing this setting.  Reducing the value will rarely result
### in a net speedup.
### This is an expert setting.  Must be a power of 2.
### l2p-page-size is 8192 entries by default.
# l2p-page-size = 8192
###
### The phys-to-log index maps positions within the rev or pack file to
### to data items,  i.e. describes what piece of information is being
### stored at any particular offset.  The index describes the rev file
### in chunks (pages) and keeps a global list of all those pages.  Large
### pages mean a shorter page table but a larger per-page description of
### data items in it.  The latency sweetspot depends on the change size
### distribution but covers a relatively wide range.
### If the repository contains very large files,  i.e. individual changes
### of tens of MB each,  increasing the page size will shorten the index
### file at the expense of a slightly increased latency in sections with
### smaller changes.
### For source code repositories, this should be about 16x the block-size.
### Must be a power of 2.
### p2l-page-size is given in kBytes and with a default of 1024 kBytes.
# p2l-page-size = 1024
    format  uuid    current Format  Found format '%d', only created by unreleased dev builds; see http://subversion.apache.org/docs/release-notes/1.7#revprop-packing   Expected FS format between '1' and '%d'; found format '%d'  Can't read first lin